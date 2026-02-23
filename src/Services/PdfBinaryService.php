<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services;

use DOMDocument;
use Xfa\Pdf\Exceptions\InvalidPdfException;
use Xfa\Pdf\Exceptions\NoXfaDataException;

class PdfBinaryService
{
    private string $binary = '';

    private string $filePath = '';

    /** @var array<int, array<string, mixed>> */
    private array $datasetStreams = [];

    /**
     * Load a PDF from a file path.
     */
    public function load(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw InvalidPdfException::fileNotFound($filePath);
        }

        if (!is_readable($filePath)) {
            throw InvalidPdfException::unreadable($filePath);
        }

        $this->filePath = $filePath;
        $this->binary = file_get_contents($filePath);
        $this->datasetStreams = [];

        return $this;
    }

    /**
     * Load a PDF from a binary string.
     */
    public function loadBinary(string $binary): self
    {
        $this->binary = $binary;
        $this->filePath = '';
        $this->datasetStreams = [];

        return $this;
    }

    /**
     * Discover all XFA dataset streams in the PDF binary.
     *
     * Finds stream/endstream blocks, decompresses them, and identifies
     * those containing <xfa:datasets>. Deduplicates by object number,
     * keeping the last occurrence (for incremental PDF updates).
     */
    public function discoverStreams(): void
    {
        if (!empty($this->datasetStreams)) {
            return;
        }

        $binary = $this->binary;
        $endMarker = 'endstream';
        $allStreams = [];

        // Find all stream boundaries using regex to handle variations
        $streamStarts = [];
        $offset = 0;

        while (preg_match('/>>[\r\n]*stream[\r\n]/', $binary, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $markerEnd = $m[0][1] + strlen($m[0][0]);
            $streamStarts[] = [
                'content_from' => $markerEnd,
                'header_context_start' => max(0, $m[0][1] - 300),
                'marker_start' => $m[0][1],
            ];
            $offset = $markerEnd;
        }

        foreach ($streamStarts as $ss) {
            $from = $ss['content_from'];

            // Find the object number and declared length from the header.
            // Use the LAST "N 0 obj" match (closest to the stream marker)
            // to avoid picking up a preceding object's number.
            $contextStart = $ss['header_context_start'];
            $context = substr($binary, $contextStart, $ss['marker_start'] - $contextStart);
            $objHeaderStart = 0;

            if (preg_match_all('/(\d+) 0 obj/s', $context, $objMatches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($objMatches[0]);
                $objHeaderStart = $contextStart + $lastMatch[1];
            }

            $headerContext = substr($binary, $objHeaderStart, $from - $objHeaderStart);
            preg_match('/(\d+) 0 obj/', $headerContext, $numMatch);
            $objNum = isset($numMatch[1]) ? intval($numMatch[1]) : 0;

            preg_match('/\/Length\s+(\d+)/', $headerContext, $lenMatch);
            $declaredLength = isset($lenMatch[1]) ? intval($lenMatch[1]) : 0;

            // Use declared length when available to extract exact stream bytes.
            // Fallback to endstream marker search if no length declared.
            if ($declaredLength > 0) {
                $raw = substr($binary, $from, $declaredLength);
                $endPos = $from + $declaredLength;
            } else {
                $endPos = strpos($binary, $endMarker, $from);
                if ($endPos === false) {
                    continue;
                }
                $raw = substr($binary, $from, $endPos - $from);
            }

            $decompressed = $this->decompressStream($raw);

            if ($decompressed && strpos($decompressed, '<xfa:datasets') !== false) {
                $allStreams[] = [
                    'obj_num' => $objNum,
                    'obj_header_start' => $objHeaderStart,
                    'stream_content_start' => $from,
                    'stream_content_end' => $endPos,
                    'declared_length' => $declaredLength,
                    'xml' => $decompressed,
                ];
            }
        }

        // Deduplicate by object number — keep the LAST occurrence of each,
        // since incremental PDF updates append newer versions at the end.
        $byObjNum = [];
        foreach ($allStreams as $stream) {
            $byObjNum[$stream['obj_num']] = $stream;
        }
        $this->datasetStreams = array_values($byObjNum);
    }

    /**
     * Decompress a raw PDF stream, stripping leading whitespace bytes.
     *
     * Handles both zlib (gzuncompress) and raw deflate (zlib_decode) formats.
     */
    public function decompressStream(string $raw): ?string
    {
        $byteArray = unpack('C*', $raw);

        if (empty($byteArray)) {
            return null;
        }

        // Strip leading newline/carriage return
        if ($byteArray[1] == 10) {
            $byteArray = array_slice($byteArray, 1);
        } elseif ($byteArray[1] == 13 && isset($byteArray[2]) && $byteArray[2] == 10) {
            $byteArray = array_slice($byteArray, 2);
        }

        if (empty($byteArray)) {
            return null;
        }

        $packed = call_user_func_array('pack', array_merge(['C*'], $byteArray));

        $result = @gzuncompress($packed);
        if ($result === false) {
            $result = @zlib_decode($packed);
        }

        return $result ?: null;
    }

    /**
     * Extract trailer information from the PDF for incremental updates.
     *
     * @return array{prev_xref_offset: int, root: ?string, info: ?string, size: ?string, id: ?string}
     */
    public function extractTrailerInfo(): array
    {
        $binary = $this->binary;
        $binaryLen = strlen($binary);

        preg_match_all('/startxref\s*(\d+)/s', $binary, $matches);
        $lastStartXref = intval(end($matches[1]));

        $info = [
            'prev_xref_offset' => $lastStartXref,
            'root' => null,
            'info' => null,
            'size' => null,
            'id' => null,
        ];

        // Try to read the xref at the declared offset
        if ($lastStartXref < $binaryLen) {
            $chunk = substr($binary, $lastStartXref, 1000);

            if (preg_match('/^\d+ \d+ obj/', $chunk)) {
                // Cross-reference stream object
                if (preg_match('/\/Root\s+(\d+ \d+ R)/', $chunk, $m)) $info['root'] = $m[1];
                if (preg_match('/\/Info\s+(\d+ \d+ R)/', $chunk, $m)) $info['info'] = $m[1];
                if (preg_match('/\/Size\s+(\d+)/', $chunk, $m)) $info['size'] = $m[1];
                if (preg_match('/\/ID\s*\[([^\]]+)\]/', $chunk, $m)) $info['id'] = $m[1];
            } elseif (strpos($chunk, 'xref') === 0) {
                // Traditional xref table
                $trailerPos = strpos($binary, 'trailer', $lastStartXref);
                if ($trailerPos !== false) {
                    $trailerChunk = substr($binary, $trailerPos, 1000);
                    if (preg_match('/\/Root\s+(\d+ \d+ R)/', $trailerChunk, $m)) $info['root'] = $m[1];
                    if (preg_match('/\/Info\s+(\d+ \d+ R)/', $trailerChunk, $m)) $info['info'] = $m[1];
                    if (preg_match('/\/Size\s+(\d+)/', $trailerChunk, $m)) $info['size'] = $m[1];
                    if (preg_match('/\/ID\s*\[([^\]]+)\]/', $trailerChunk, $m)) $info['id'] = $m[1];
                }
            }
        }

        // Fallback: scan the last portion of the file
        if (!$info['root']) {
            $tail = substr($binary, max(0, $binaryLen - 2000));
            if (preg_match('/\/Root\s+(\d+ \d+ R)/', $tail, $m)) $info['root'] = $m[1];
            if (preg_match('/\/Info\s+(\d+ \d+ R)/', $tail, $m)) $info['info'] = $m[1];
            if (preg_match('/\/Size\s+(\d+)/', $tail, $m)) $info['size'] = $m[1];
            if (preg_match('/\/ID\s*\[([^\]]+)\]/', $tail, $m)) $info['id'] = $m[1];
        }

        // Last resort: search entire file
        if (!$info['root']) {
            if (preg_match('/\/Root\s+(\d+ \d+ R)/', $binary, $m)) $info['root'] = $m[1];
        }
        if (!$info['size']) {
            if (preg_match_all('/\/Size\s+(\d+)/', $binary, $m)) $info['size'] = (string) max($m[1]);
        }

        return $info;
    }

    /**
     * Group consecutive keys for xref table generation.
     * e.g. [62 => 100, 63 => 200, 103 => 300] => [62 => [100, 200], 103 => [300]]
     *
     * @param array<int, int> $map
     * @return array<int, array<int, int>>
     */
    public function groupConsecutiveKeys(array $map): array
    {
        $groups = [];
        $prevKey = null;
        $currentStart = null;

        foreach ($map as $key => $value) {
            if ($prevKey === null || $key !== $prevKey + 1) {
                $currentStart = $key;
            }
            $groups[$currentStart][] = $value;
            $prevKey = $key;
        }

        return $groups;
    }

    /**
     * Write modified XFA datasets XML back to the PDF using incremental update.
     *
     * Appends new object versions at the end of the file with a new xref table,
     * leaving the original PDF content completely untouched.
     */
    public function writeIncrementalUpdate(DOMDocument $dom, ?string $outputPath = null): bool
    {
        $outputPath = $outputPath ?: $this->filePath;

        $newXml = $dom->saveXML($dom->documentElement);
        $newCompressed = gzcompress($newXml);
        $newLength = strlen($newCompressed);

        $this->discoverStreams();

        if (empty($this->datasetStreams)) {
            throw NoXfaDataException::noDatasetsFound();
        }

        $trailerInfo = $this->extractTrailerInfo();

        $originalLength = strlen($this->binary);
        $append = "\n";

        $newObjectOffsets = [];

        foreach ($this->datasetStreams as $stream) {
            $objNum = $stream['obj_num'];
            $objOffset = $originalLength + strlen($append);
            $newObjectOffsets[$objNum] = $objOffset;

            $append .= "{$objNum} 0 obj\n";
            $append .= "<</Filter[/FlateDecode]/Length {$newLength}/Type/EmbeddedFile>>\n";
            $append .= "stream\n";
            $append .= $newCompressed;
            $append .= "\nendstream\n";
            $append .= "endobj\n";
        }

        // Build traditional xref table
        $xrefOffset = $originalLength + strlen($append);
        $append .= "xref\n";

        ksort($newObjectOffsets);
        $groups = $this->groupConsecutiveKeys($newObjectOffsets);

        foreach ($groups as $startObj => $offsets) {
            $count = count($offsets);
            $append .= "{$startObj} {$count}\n";
            foreach ($offsets as $offset) {
                $append .= sprintf("%010d 00000 n \n", $offset);
            }
        }

        // Build trailer
        $append .= "trailer\n";
        $append .= "<<";
        $append .= "/Size " . $trailerInfo['size'];
        $append .= "/Root " . $trailerInfo['root'];
        if ($trailerInfo['info']) {
            $append .= "/Info " . $trailerInfo['info'];
        }
        $append .= "/Prev " . $trailerInfo['prev_xref_offset'];
        if ($trailerInfo['id']) {
            $append .= "/ID[" . $trailerInfo['id'] . "]";
        }
        $append .= ">>\n";
        $append .= "startxref\n";
        $append .= $xrefOffset . "\n";
        $append .= "%%EOF\n";

        if ($outputPath === $this->filePath && $this->filePath !== '') {
            $result = file_put_contents($outputPath, $append, FILE_APPEND);
        } else {
            $result = file_put_contents($outputPath, $this->binary . $append);
        }

        return $result !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDatasetStreams(): array
    {
        return $this->datasetStreams;
    }

    public function getBinary(): string
    {
        return $this->binary;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
