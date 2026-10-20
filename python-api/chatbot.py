from groq import Groq
from sentence_transformers import SentenceTransformer
from opensearchpy import OpenSearch
from config import (
    OPENSEARCH_HOST, OPENSEARCH_PORT, OPENSEARCH_INDEX,
    GROQ_API_KEY, GROQ_MODEL,
    EMBEDDING_MODEL, TOP_K, SYSTEM_PROMPT
)


class Chatbot:
    def __init__(self):
        self.embedder   = SentenceTransformer(EMBEDDING_MODEL)
        self.groq       = Groq(api_key=GROQ_API_KEY)
        self.os_client  = OpenSearch(
            hosts=[{"host": OPENSEARCH_HOST, "port": OPENSEARCH_PORT}],
            http_compress=True,
        )
        self._ensure_index()

    def _ensure_index(self):
        if self.os_client.indices.exists(index=OPENSEARCH_INDEX):
            return
        self.os_client.indices.create(
            index=OPENSEARCH_INDEX,
            body={
                "settings": {"index": {"knn": True}},
                "mappings": {
                    "properties": {
                        "content":    {"type": "text"},
                        "source":     {"type": "keyword"},
                        "data_type":  {"type": "keyword"},
                        "user_id":    {"type": "integer"},
                        "session_id": {"type": "keyword"},
                        "embedding": {
                            "type":      "knn_vector",
                            "dimension": 384,
                            "method": {
                                "name":       "hnsw",
                                "space_type": "cosinesimil",
                                "engine":     "nmslib",
                            },
                        },
                    }
                },
            },
        )

    def _embed(self, text: str) -> list:
        return self.embedder.encode(text).tolist()

    def _search_user(self, query: str, user_id: int) -> list[str]:
        vector = self._embed(query)
        body = {
            "query": {
                "bool": {
                    "must": [{"knn": {"embedding": {"vector": vector, "k": 3}}}],
                    "filter": [
                        {"term": {"data_type": "user"}},
                        {"term": {"user_id": user_id}},
                    ],
                }
            },
            "size": 3,
        }
        resp = self.os_client.search(index=OPENSEARCH_INDEX, body=body)
        return [hit["_source"]["content"] for hit in resp["hits"]["hits"]]

    def _search_public(self, query: str, k: int) -> list[str]:
        vector = self._embed(query)
        body = {
            "query": {
                "bool": {
                    "must":   [{"knn": {"embedding": {"vector": vector, "k": k}}}],
                    "filter": [{"term": {"data_type": "public"}}],
                }
            },
            "size": k,
        }
        resp = self.os_client.search(index=OPENSEARCH_INDEX, body=body)
        return [hit["_source"]["content"] for hit in resp["hits"]["hits"]]

    def chat(
        self,
        message: str,
        session_id: str,
        user_id: int | None = None,
        history: list[dict] | None = None,
        session_title: str | None = None,
    ) -> str:
        user_chunks   = self._search_user(message, user_id) if user_id else []
        public_chunks = self._search_public(message, k=max(TOP_K - len(user_chunks), 2))
        chunks        = user_chunks + public_chunks

        context = "\n\n".join(chunks) if chunks else "No specific context found in the knowledge base."

        user_context_note = (
            "\n\nNote: Some context above is from this player's personal notes — treat it as their specific game data."
            if user_chunks else ""
        )

        system_content = SYSTEM_PROMPT
        if session_title:
            system_content += f"\n\nThis conversation is about: {session_title}"

        messages = [{"role": "system", "content": system_content}]

        # Inject prior conversation turns so the model has memory of this session
        if history:
            messages.extend(history)

        # Current question augmented with RAG context
        messages.append({
            "role": "user",
            "content": (
                f"Context from Minecraft knowledge base:\n{context}"
                f"{user_context_note}\n\n"
                f"Player question: {message}"
            ),
        })

        completion = self.groq.chat.completions.create(
            model=GROQ_MODEL,
            messages=messages,
            max_tokens=1024,
            temperature=0.7,
        )
        return completion.choices[0].message.content
