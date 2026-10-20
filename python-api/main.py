# MinecraftChat AI API - Main Application
# Author: Kenny Merrill
# Description: FastAPI application for chat, indexing, and web crawling

import os
from contextlib import asynccontextmanager
from typing import Optional

from fastapi import FastAPI, HTTPException
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.responses import JSONResponse
from pydantic import BaseModel

from chatbot import Chatbot

INTERNAL_KEY = os.environ.get("INTERNAL_API_KEY", "")

chatbot: Chatbot | None = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    global chatbot
    chatbot = Chatbot()
    yield


app = FastAPI(
    title="MinecraftChat AI API",
    lifespan=lifespan,
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)


class InternalKeyMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request, call_next):
        if INTERNAL_KEY and request.url.path != "/health":
            if request.headers.get("X-Internal-Key") != INTERNAL_KEY:
                return JSONResponse({"detail": "Forbidden"}, status_code=403)
        return await call_next(request)


app.add_middleware(InternalKeyMiddleware)


class HistoryMessage(BaseModel):
    role: str
    content: str


class ChatRequest(BaseModel):
    message: str
    session_id: str
    user_id: Optional[int] = None
    history: Optional[list[HistoryMessage]] = None
    session_title: Optional[str] = None


class IndexRequest(BaseModel):
    path: Optional[str] = None
    user_id: Optional[int] = None
    session_id: Optional[str] = None
    data_type: str = "public"


class DocumentIndexRequest(BaseModel):
    text: str
    source: str = "manual"
    data_type: str = "public"
    user_id: Optional[int] = None
    doc_id: Optional[int] = None


class CrawlRequest(BaseModel):
    start_url: str
    max_pages: int = 100


@app.get("/health")
def health():
    from config import GROQ_API_KEY
    return {"status": "ok", "groq": bool(GROQ_API_KEY)}


@app.post("/chat")
def chat(req: ChatRequest):
    try:
        history = [{"role": m.role, "content": m.content} for m in req.history] if req.history else []
        response = chatbot.chat(
            message=req.message,
            session_id=req.session_id,
            user_id=req.user_id,
            history=history,
            session_title=req.session_title,
        )
        return {"response": response, "session_id": req.session_id}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/index")
def index_docs(req: IndexRequest):
    try:
        from indexer import Indexer
        indexer = Indexer()
        count = indexer.index_directory(
            path=req.path or "/app/docs",
            data_type=req.data_type,
            user_id=req.user_id,
            session_id=req.session_id,
        )
        return {"indexed": count, "data_type": req.data_type}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/index/document")
def index_document(req: DocumentIndexRequest):
    try:
        from indexer import Indexer
        indexer = Indexer()
        count = indexer.index_text(
            text=req.text,
            source=req.source,
            data_type=req.data_type,
            user_id=req.user_id,
            doc_id=req.doc_id,
        )
        return {"chunks_indexed": count}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/crawl")
def crawl(req: CrawlRequest):
    try:
        from crawler import Crawler
        crawler = Crawler()
        count = crawler.crawl(req.start_url, max_pages=req.max_pages)
        return {"indexed": count, "pages_visited": len(crawler.visited)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.delete("/index/document/{doc_id}")
def delete_document(doc_id: int):
    try:
        from indexer import Indexer
        indexer = Indexer()
        deleted = indexer.delete_doc(doc_id)
        return {"deleted": deleted}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


