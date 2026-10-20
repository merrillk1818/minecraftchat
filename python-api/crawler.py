import re
import time
import requests
from urllib.parse import urljoin, urlparse
from bs4 import BeautifulSoup
from indexer import Indexer


ALLOWED_DOMAINS = {"minecraft.wiki", "minecraft.fandom.com"}
REQUEST_DELAY   = 1.5  # seconds between requests — be a good citizen


class Crawler:
    def __init__(self):
        self.indexer  = Indexer()
        self.visited  = set()
        self.session  = requests.Session()
        self.session.headers["User-Agent"] = "MinecraftChatBot/1.0 (educational crawler)"

    def _is_allowed(self, url: str) -> bool:
        domain = urlparse(url).netloc.lstrip("www.")
        return domain in ALLOWED_DOMAINS

    def _extract_text(self, html: str) -> str:
        soup = BeautifulSoup(html, "html.parser")
        for tag in soup(["script", "style", "nav", "footer", "aside", "table"]):
            tag.decompose()
        text = soup.get_text(separator=" ")
        return re.sub(r"\s+", " ", text).strip()

    def crawl(self, start_url: str, max_pages: int = 100) -> int:
        queue = [start_url]
        total_indexed = 0

        while queue and len(self.visited) < max_pages:
            url = queue.pop(0)
            if url in self.visited or not self._is_allowed(url):
                continue

            try:
                resp = self.session.get(url, timeout=10)
                resp.raise_for_status()
            except Exception as e:
                print(f"Failed to fetch {url}: {e}")
                continue

            self.visited.add(url)
            text = self._extract_text(resp.text)

            if len(text) > 200:
                total_indexed += self.indexer.index_text(
                    text, source=url, data_type="public"
                )
                print(f"Indexed: {url}")

            soup = BeautifulSoup(resp.text, "html.parser")
            for a in soup.find_all("a", href=True):
                full_url = urljoin(url, a["href"]).split("#")[0]
                if full_url not in self.visited and self._is_allowed(full_url):
                    queue.append(full_url)

            time.sleep(REQUEST_DELAY)

        return total_indexed
