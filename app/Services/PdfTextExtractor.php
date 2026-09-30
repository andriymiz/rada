<?php

namespace App\Services;

use RuntimeException;

class PdfTextExtractor
{
    /**
     * @return list<string>
     */
    public function pages(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read the PDF.');
        }

        preg_match_all('/(\d+)\s+0\s+obj(.*?)endobj/s', $contents, $matches, PREG_SET_ORDER);
        $objects = [];

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        $maps = [];

        foreach ($objects as $number => $object) {
            if (! str_contains($object, '/ToUnicode')) {
                continue;
            }

            preg_match('/stream\s*\r?\n(.*?)endstream/s', $object, $stream);
            $decoded = isset($stream[1]) ? @gzuncompress(rtrim($stream[1], "\r\n")) : false;

            if ($decoded === false) {
                continue;
            }

            $maps[$number] = $this->parseCMap($decoded);
        }

        $pages = [];

        foreach ($objects as $object) {
            if (! preg_match('/\/Type\s*\/Page\b/', $object) ||
                ! preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $object, $contentReference)) {
                continue;
            }

            $fonts = [];

            if (preg_match('/\/Font\s*<<(.*?)>>/s', $object, $fontDictionary)) {
                preg_match_all('/\/(F\d+)\s+(\d+)\s+0\s+R/', $fontDictionary[1], $fontReferences, PREG_SET_ORDER);

                foreach ($fontReferences as $fontReference) {
                    if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $objects[(int) $fontReference[2]] ?? '', $unicodeReference)) {
                        $fonts[$fontReference[1]] = $maps[(int) $unicodeReference[1]] ?? [];
                    }
                }
            }

            $pages[] = $this->extractPage($objects[(int) $contentReference[1]] ?? '', $fonts);
        }

        return array_values(array_filter($pages, static fn (string $page): bool => trim($page) !== ''));
    }

    /**
     * @return array<int, string>
     */
    private function parseCMap(string $cmap): array
    {
        $map = [];

        foreach (preg_split('/\R/', $cmap) ?: [] as $line) {
            preg_match_all('/<([0-9A-F]+)>/', $line, $values);

            if (count($values[1]) === 2) {
                $map[hexdec($values[1][0])] = mb_chr(hexdec($values[1][1]), 'UTF-8');
            }

            if (count($values[1]) === 3) {
                [$start, $end, $destination] = array_map('hexdec', $values[1]);

                for ($code = $start; $code <= $end; $code++) {
                    $map[$code] = mb_chr($destination + $code - $start, 'UTF-8');
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, array<int, string>> $fonts
     */
    private function extractPage(string $object, array $fonts): string
    {
        preg_match('/stream\s*\r?\n(.*?)endstream/s', $object, $stream);
        $decoded = isset($stream[1]) ? @gzuncompress(rtrim($stream[1], "\r\n")) : false;

        if ($decoded === false) {
            return '';
        }

        $font = 'F0';
        $text = '';

        preg_match_all(
            '/\/(F\d+)\s+[\d.]+\s+Tf|<([0-9A-Fa-f]+)>\s*Tj|\[(.*?)\]\s*TJ|T\*/s',
            $decoded,
            $tokens,
            PREG_SET_ORDER,
        );

        foreach ($tokens as $token) {
            if ($token[1] !== '') {
                $font = $token[1];
                continue;
            }

            if ($token[4] === 'T*') {
                $text .= "\n";
                continue;
            }

            $hexStrings = $token[2] !== '' ? [$token[2]] : preg_match_all('/<([0-9A-Fa-f]+)>/', $token[3], $parts)
                ? $parts[1]
                : [];

            foreach ($hexStrings as $hex) {
                $characters = $fonts[$font] ?? [];
                $bytes = hex2bin($hex) ?: '';

                for ($offset = 0; $offset + 1 < strlen($bytes); $offset += 2) {
                    $code = unpack('n', substr($bytes, $offset, 2))[1];
                    $text .= $characters[$code] ?? '';
                }
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
