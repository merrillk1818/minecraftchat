<?php

namespace App\Console\Commands\Concerns;

trait ExtractsText
{
    private function extractText(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return match(true) {
            in_array($ext, ['html', 'htm']) => $this->cleanHtml(file_get_contents($file)),
            $ext === 'pdf'                  => $this->extractPdf($file),
            default                         => file_get_contents($file),
        };
    }

    private function cleanHtml(string $html): string
    {
        $html = preg_replace('/<(script|style|nav|header|footer|aside)[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function extractPdf(string $file): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($file);
        $text   = $pdf->getText();
        $text   = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
