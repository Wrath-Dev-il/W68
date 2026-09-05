<?php

namespace App\Support;

use RuntimeException;

class SimpleXlsxReader
{
    public function rows(string $path): array
    {
        $size = @filesize($path);
        $archive = $size !== false
            ? @file_get_contents($path, false, null, 0, $size)
            : @file_get_contents($path);
        if ($archive === false) {
            throw new RuntimeException('Unable to read the uploaded Excel file.');
        }

        $entries = $this->readCentralDirectory($archive);
        $sharedStrings = $this->parseSharedStrings($this->extractEntry($archive, $entries, 'xl/sharedStrings.xml') ?? '');
        $sheetPath = $this->firstWorksheetPath($entries);

        if (!$sheetPath) {
            throw new RuntimeException('The Excel file does not contain a worksheet.');
        }

        $sheetXml = $this->extractEntry($archive, $entries, $sheetPath);
        if ($sheetXml === null) {
            throw new RuntimeException('Unable to read the first worksheet.');
        }

        return $this->parseSheetRows($sheetXml, $sharedStrings);
    }

    private function readCentralDirectory(string $archive): array
    {
        $eocdOffset = $this->findEndOfCentralDirectory($archive);
        if ($eocdOffset === false) {
            throw new RuntimeException('The uploaded file is not a valid XLSX archive.');
        }

        $entryCount = $this->readUInt16($archive, $eocdOffset + 10);
        $centralDirectorySize = $this->readUInt32($archive, $eocdOffset + 12);
        $centralDirectoryOffset = $this->readUInt32($archive, $eocdOffset + 16);

        if ($entryCount === 0xffff || $centralDirectoryOffset === 0xffffffff || $centralDirectorySize === 0xffffffff) {
            throw new RuntimeException('Zip64 XLSX files are not supported by this importer.');
        }

        $entries = [];
        $offset = $centralDirectoryOffset;
        $end = $centralDirectoryOffset + $centralDirectorySize;

        while ($offset < $end && substr($archive, $offset, 4) === "PK\x01\x02") {
            $method = $this->readUInt16($archive, $offset + 10);
            $compressedSize = $this->readUInt32($archive, $offset + 20);
            $uncompressedSize = $this->readUInt32($archive, $offset + 24);
            $nameLength = $this->readUInt16($archive, $offset + 28);
            $extraLength = $this->readUInt16($archive, $offset + 30);
            $commentLength = $this->readUInt16($archive, $offset + 32);
            $localOffset = $this->readUInt32($archive, $offset + 42);
            $name = str_replace('\\', '/', substr($archive, $offset + 46, $nameLength));

            $entries[$name] = [
                'method' => $method,
                'compressed_size' => $compressedSize,
                'uncompressed_size' => $uncompressedSize,
                'local_offset' => $localOffset,
            ];

            $offset += 46 + $nameLength + $extraLength + $commentLength;
        }

        return $entries;
    }

    private function findEndOfCentralDirectory(string $archive): int|false
    {
        $minimumOffset = max(0, strlen($archive) - 66000);

        for ($offset = strlen($archive) - 22; $offset >= $minimumOffset; $offset--) {
            if (
                $archive[$offset] === 'P'
                && $archive[$offset + 1] === 'K'
                && ord($archive[$offset + 2]) === 5
                && ord($archive[$offset + 3]) === 6
            ) {
                return $offset;
            }
        }

        return false;
    }

    private function extractEntry(string $archive, array $entries, string $name): ?string
    {
        if (!isset($entries[$name])) {
            return null;
        }

        $entry = $entries[$name];
        $offset = $entry['local_offset'];

        if (substr($archive, $offset, 4) !== "PK\x03\x04") {
            throw new RuntimeException("Invalid XLSX local header for {$name}.");
        }

        $nameLength = $this->readUInt16($archive, $offset + 26);
        $extraLength = $this->readUInt16($archive, $offset + 28);
        $dataOffset = $offset + 30 + $nameLength + $extraLength;
        $compressed = substr($archive, $dataOffset, $entry['compressed_size']);

        if ($entry['method'] === 0) {
            return $compressed;
        }

        if ($entry['method'] !== 8) {
            throw new RuntimeException("Unsupported XLSX compression method for {$name}.");
        }

        $inflated = @gzinflate($compressed);
        if ($inflated === false || strlen($inflated) !== $entry['uncompressed_size']) {
            throw new RuntimeException("Unable to decompress {$name} from the XLSX file.");
        }

        return $inflated;
    }

    private function firstWorksheetPath(array $entries): ?string
    {
        if (isset($entries['xl/worksheets/sheet1.xml'])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $worksheets = array_values(array_filter(
            array_keys($entries),
            fn (string $name): bool => str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')
        ));

        sort($worksheets, SORT_NATURAL);

        return $worksheets[0] ?? null;
    }

    private function parseSharedStrings(string $xml): array
    {
        $strings = [];
        $offset = 0;

        while (($start = strpos($xml, '<si', $offset)) !== false) {
            $startClose = strpos($xml, '>', $start);
            $end = strpos($xml, '</si>', $startClose);
            if ($startClose === false || $end === false) {
                break;
            }

            $siXml = substr($xml, $startClose + 1, $end - $startClose - 1);
            $strings[] = $this->extractTextRuns($siXml);
            $offset = $end + 5;
        }

        return $strings;
    }

    private function parseSheetRows(string $xml, array $sharedStrings): array
    {
        $rows = [];
        $offset = strpos($xml, '<sheetData');

        if ($offset === false) {
            return $rows;
        }

        while (($rowStart = strpos($xml, '<row', $offset)) !== false) {
            $rowTagEnd = strpos($xml, '>', $rowStart);
            $rowEnd = strpos($xml, '</row>', $rowTagEnd);

            if ($rowTagEnd === false || $rowEnd === false) {
                break;
            }

            $rowXml = substr($xml, $rowTagEnd + 1, $rowEnd - $rowTagEnd - 1);
            $rows[] = $this->parseRowCells($rowXml, $sharedStrings);
            $offset = $rowEnd + 6;
        }

        return $rows;
    }

    private function parseRowCells(string $rowXml, array $sharedStrings): array
    {
        $cells = [];
        $offset = 0;

        while (($cellStart = strpos($rowXml, '<c', $offset)) !== false) {
            $tagEnd = strpos($rowXml, '>', $cellStart);
            if ($tagEnd === false) {
                break;
            }

            $tag = substr($rowXml, $cellStart, $tagEnd - $cellStart + 1);
            $attributes = $this->parseAttributes($tag);
            $isSelfClosing = str_ends_with(rtrim($tag), '/>');
            $cellEnd = $isSelfClosing ? $tagEnd : strpos($rowXml, '</c>', $tagEnd);

            if ($cellEnd === false) {
                break;
            }

            $content = $isSelfClosing ? '' : substr($rowXml, $tagEnd + 1, $cellEnd - $tagEnd - 1);
            $reference = $attributes['r'] ?? '';
            $column = $this->columnLetters($reference);

            if ($column !== '') {
                $cells[$column] = $this->cellValue($content, $attributes['t'] ?? '', $sharedStrings);
            }

            $offset = $isSelfClosing ? $tagEnd + 1 : $cellEnd + 4;
        }

        return $cells;
    }

    private function cellValue(string $content, string $type, array $sharedStrings): string
    {
        if ($type === 'inlineStr') {
            return $this->extractTextRuns($content);
        }

        $value = $this->between($content, '<v>', '</v>');
        if ($value === null) {
            return '';
        }

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $this->decodeXml($value);
    }

    private function extractTextRuns(string $xml): string
    {
        $value = '';
        $offset = 0;

        while (($start = strpos($xml, '<t', $offset)) !== false) {
            $startClose = strpos($xml, '>', $start);
            $end = strpos($xml, '</t>', $startClose);

            if ($startClose === false || $end === false) {
                break;
            }

            $value .= substr($xml, $startClose + 1, $end - $startClose - 1);
            $offset = $end + 4;
        }

        return $this->decodeXml($value);
    }

    private function parseAttributes(string $tag): array
    {
        preg_match_all('/([A-Za-z_:][A-Za-z0-9_.:-]*)="([^"]*)"/', $tag, $matches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($matches as $match) {
            $attributes[$match[1]] = $this->decodeXml($match[2]);
        }

        return $attributes;
    }

    private function columnLetters(string $reference): string
    {
        $letters = '';
        $length = strlen($reference);

        for ($i = 0; $i < $length; $i++) {
            $char = $reference[$i];
            if ($char >= 'A' && $char <= 'Z') {
                $letters .= $char;
                continue;
            }

            if ($char >= 'a' && $char <= 'z') {
                $letters .= strtoupper($char);
                continue;
            }

            break;
        }

        return $letters;
    }

    private function between(string $text, string $startToken, string $endToken): ?string
    {
        $start = strpos($text, $startToken);
        if ($start === false) {
            return null;
        }

        $start += strlen($startToken);
        $end = strpos($text, $endToken, $start);

        if ($end === false) {
            return null;
        }

        return substr($text, $start, $end - $start);
    }

    private function decodeXml(string $value): string
    {
        return trim(str_replace('_x000D_', '', html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8')));
    }

    private function readUInt16(string $data, int $offset): int
    {
        return unpack('v', substr($data, $offset, 2))[1];
    }

    private function readUInt32(string $data, int $offset): int
    {
        return unpack('V', substr($data, $offset, 4))[1];
    }
}
