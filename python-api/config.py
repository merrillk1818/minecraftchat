import os
from dotenv import load_dotenv

load_dotenv()

OPENSEARCH_HOST  = os.getenv("OPENSEARCH_HOST", "localhost")
OPENSEARCH_PORT  = int(os.getenv("OPENSEARCH_PORT", "9200"))
OPENSEARCH_INDEX = os.getenv("OPENSEARCH_INDEX", "minecraft_docs")

DB_HOST     = os.getenv("DB_HOST", "localhost")
DB_PORT     = int(os.getenv("DB_PORT", "3306"))
DB_NAME     = os.getenv("DB_NAME", "minecraftchat")
DB_USER     = os.getenv("DB_USER", "mcuser")
DB_PASSWORD = os.getenv("DB_PASSWORD", "")

GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL   = os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile")

EMBEDDING_MODEL = os.getenv("EMBEDDING_MODEL", "all-MiniLM-L6-v2")
EMBEDDING_DIM   = 384

CHUNK_SIZE    = int(os.getenv("CHUNK_SIZE", "500"))
CHUNK_OVERLAP = int(os.getenv("CHUNK_OVERLAP", "50"))
TOP_K         = int(os.getenv("TOP_K", "5"))

SYSTEM_PROMPT = (
    "You are MinecraftBot, an expert AI assistant for Minecraft players. "
    "You have deep knowledge of all Minecraft versions including Java and Bedrock editions. "
    "You help players with crafting recipes, survival strategies, redstone circuits, building tips, "
    "mob mechanics, biome exploration, and everything else in the Minecraft universe. "
    "Be friendly, accurate, and specific with block names, item names, and coordinates when relevant. "
    "If a question is unrelated to Minecraft, politely redirect the conversation back to Minecraft."
)
