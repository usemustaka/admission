<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a real Excel workbook (.xlsx) to the browser: a bold header row, sensible column widths and text cells that
 * stay text, so NIK and phone numbers keep their leading zeros. Unlike CSV it opens in the right columns whatever the
 * list separator of the computer's region is, and text is never run as a formula.
 */
class XlsxExport
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<scalar|null>>  $rows  each row in the order of the headers; strings stay text, numbers stay numbers
     * @param  string  $sheetName  at most 31 characters, without `[]:*?/\`
     */
    public static function download(string $fileName, array $headers, iterable $rows, string $sheetName = 'Data'): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $sheetName): void {
            $options = new Options(DEFAULT_COLUMN_WIDTH: 18);
            $options->setColumnWidth(30, 1);

            $writer = new Writer($options);
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName(substr((string) preg_replace('/[\[\]:*?\/\\\\]/', '', $sheetName), 0, 31) ?: 'Data');

            $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)->withBackgroundColor('E7ECF3')->withShouldWrapText(true)));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn (mixed $value): mixed => $value ?? '', $row)));
            }

            $writer->close();
        }, $fileName, ['Content-Type' => self::CONTENT_TYPE]);
    }
}
