import os
import uuid
from pathlib import Path
from sentence_transformers import SentenceTransformer
from opensearchpy import OpenSearch, helpers
from config import (
    OPENSEARCH_HOST, OPENSEARCH_PORT, OPENSEARCH_INDEX,
    EMBEDDING_MODEL, CHUNK_SIZE, CHUNK_OVERLAP
)


class Indexer:
    def __init__(self):
        self.embedder = SentenceTransformer(EMBEDDING_MODEL)
        self.os_client = OpenSearch(
            hosts=[{"host": OPENSEARCH_HOST, "port": OPENSEARCH_PORT}],
            http_compress=True,
        )

    def _chunk(self, text: str) -> list[str]:
        words = text.split()
        chunks = []
        start = 0
        while start < len(words):
            end = start + CHUNK_SIZE
            chunks.append(" ".join(words[start:end]))
            start += CHUNK_SIZE - CHUNK_OVERLAP
        return [c for c in chunks if c.strip()]

    def _build_doc(self, chunk: str, source: str, data_type: str,
                   user_id: int | None, session_id: str | None,
                   doc_id: int | None = None) -> dict:
        return {
            "_index": OPENSEARCH_INDEX,
            "_id": str(uuid.uuid4()),
            "_source": {
                "content":    chunk,
                "source":     source,
                "data_type":  data_type,
                "user_id":    user_id,
                "session_id": session_id,
                "doc_id":     doc_id,
                "embedding":  self.embedder.encode(chunk).tolist(),
            },
        }

    def index_text(self, text: str, source: str = "manual",
                   data_type: str = "public", user_id: int | None = None,
                   session_id: str | None = None, doc_id: int | None = None) -> int:
        chunks = self._chunk(text)
        docs = [self._build_doc(c, source, data_type, user_id, session_id, doc_id) for c in chunks]
        if docs:
            helpers.bulk(self.os_client, docs)
        return len(docs)

    def index_file(self, filepath: str, data_type: str = "public",
                   user_id: int | None = None, session_id: str | None = None) -> int:
        text = Path(filepath).read_text(encoding="utf-8", errors="ignore")
        return self.index_text(text, source=filepath, data_type=data_type,
                               user_id=user_id, session_id=session_id)

    def index_directory(self, path: str = "/app/docs", data_type: str = "public",
                        user_id: int | None = None, session_id: str | None = None) -> int:
        total = 0
        for root, _, files in os.walk(path):
            for fname in files:
                if fname.endswith((".txt", ".md", ".html")):
                    total += self.index_file(
                        os.path.join(root, fname),
                        data_type=data_type,
                        user_id=user_id,
                        session_id=session_id,
                    )
        return total

    def delete_doc(self, doc_id: int) -> int:
        resp = self.os_client.delete_by_query(
            index=OPENSEARCH_INDEX,
            body={"query": {"term": {"doc_id": doc_id}}},
        )
        return resp.get("deleted", 0)

    def delete_user_data(self, user_id: int) -> int:
        resp = self.os_client.delete_by_query(
            index=OPENSEARCH_INDEX,
            body={"query": {"term": {"user_id": user_id}}},
        )
        return resp.get("deleted", 0)
