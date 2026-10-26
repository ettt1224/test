<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel 讀寫封裝（OpenSpout，串流處理，大檔也不會吃光記憶體）。
 */
class ExcelService
{
    /**
     * 產生 xlsx 暫存檔並回傳路徑。
     *
     * @param  array<string, array{headers: array, rows: iterable, title?: array}>  $sheets  工作表名稱 => 內容
     */
    public function write(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_').'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);

        $headerStyle = (new Style())->setFontBold()->setBackgroundColor('DDEBF7');
        $titleStyle = (new Style())->setFontBold()->setFontSize(13);

        $first = true;
        foreach ($sheets as $name => $sheet) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $first = false;
            $writer->getCurrentSheet()->setName(mb_substr($name, 0, 31));

            foreach ($sheet['title'] ?? [] as $line) {
                $writer->addRow(Row::fromValues((array) $line, $titleStyle));
            }
            $writer->addRow(Row::fromValues($sheet['headers'], $headerStyle));
            foreach ($sheet['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * 讀取第一個工作表，回傳以表頭為 key 的資料列（略過整列空白）。
     *
     * @return array<int, array<string, mixed>> 列號(Excel 行號) => 資料
     */
    public function readAssoc(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $result = [];
        $headers = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $values = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row->toArray());
                if ($headers === null) {
                    $headers = array_map(fn ($h) => trim((string) $h), $values);
                    continue;
                }
                if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $assoc = [];
                foreach ($headers as $i => $header) {
                    $assoc[$header] = $values[$i] ?? null;
                }
                $result[$index] = $assoc;
            }
            break;
        }
        $reader->close();

        return $result;
    }
}
