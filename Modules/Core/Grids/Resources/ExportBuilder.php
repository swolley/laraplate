<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Resources;

use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExportFormats;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Core\App\Helpers\ResponseBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ExportBuilder extends ResponseBuilder
{
    private string $fileName;

    /**
     * Set the value of data
     *
     * @param  array|Collection  $data
     * @return static
     */
    #[\Override]
    public function setData(mixed $data): static
    {
        if (!is_array($data) && !$data instanceof Collection && !$data instanceof \Exception) {
            throw new \Exception('No a valid data format');
        }

        return parent::setData($data);
    }

    /**
     * Set the value of fileName
     *
     * @return self
     */
    public function setFileName(string $fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * @return Collection<array-key, mixed>
     */
    private function makePlainData(array $visibileColumns = []): Collection
    {
        /** @var Collection<array-key, mixed> */
        $mapped_data = new Collection();
        $data = $this->data();
        foreach (($data instanceof Collection ? $data->toArray() : $data) as $row) {
            $mapped_row = [];
            foreach ($row as $table => $columns) {
                foreach ($columns as $column => $value) {
                    $field = is_string($column) ? $table . '.' . $column : $table;
                    if (empty($visibileColumns) || in_array($field, $visibileColumns)) {
                        $mapped_row[$field] = is_array($value) ? json_encode($value) : $value;
                    }
                }
            }
            $mapped_data->add($mapped_row);
        }

        return $mapped_data;
    }

    private static function randomFileName(): string
    {
        return bin2hex(\random_bytes(10)) . '-' . (new \DateTime())->getTimestamp();
    }

    private static function uniqueFileName(string $filename): string
    {
        return $filename . '-' . (new \DateTime())->format('Ymd');
    }

    /**
     * @return void
     */
    private function checkRequiredProperties()
    {
        $data = $this->data();
        if (!isset($data)) {
            throw new \Exception('Missing data');
        }
    }

    public function excel(array $visibleColumns = []): BinaryFileResponse
    {
        $this->checkRequiredProperties();
        $plain_data = static::makePlainData($visibleColumns);
        $headers = $visibleColumns ?: ($plain_data->isNotEmpty() ? array_keys($plain_data->first()) : []);

        return Excel::download(new ExcelExport($headers, $plain_data->toArray()), static::uniqueFileName($this->fileName ?? static::randomFileName()) . '.xlsx', ExportFormats::XLSX);
    }

    public function csv(array $visibleColumns = []): BinaryFileResponse
    {
        $this->checkRequiredProperties();
        $plain_data = static::makePlainData($visibleColumns);

        return Excel::download($plain_data, static::uniqueFileName($this->fileName ?? static::randomFileName()) . '.txt', ExportFormats::CSV);
    }

    public function pdf(array $visibleColumns = []): BinaryFileResponse
    {
        $this->checkRequiredProperties();
        $plain_data = static::makePlainData($visibleColumns);
        $headers = $visibleColumns ?: ($plain_data->isNotEmpty() ? array_keys($plain_data->first()) : []);
        $html = view('exports.table', ['headers' => $headers, 'data' => $plain_data])->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();
        $filename = static::uniqueFileName($this->fileName ?? static::randomFileName()) . '.pdf';
        file_put_contents($filename, $dompdf->output());
        $response = new BinaryFileResponse($filename);
        $response->trustXSendfileTypeHeader();
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, iconv('UTF-8', 'ASCII//TRANSLIT', $filename));
        $response->deleteFileAfterSend();

        return $response;
    }

    public function json(): BinaryFileResponse
    {
        $this->checkRequiredProperties();

        return response()->download(json_encode($this->data(), JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK), static::uniqueFileName($this->fileName ?? static::randomFileName()) . '.json', ['Content-Type' => 'application/json']);
    }

    // public function xml(): BinaryFileResponse
    // {
    // 	$this->checkRequiredProperties();
    // 	return response()->download(response()->xml()->getContent(), static::uniqueFileName($this->fileName ?? static::randomFileName()) . '.xml', ['Content-Type' => 'application/xml']);
    // }

    public function getResponse()
    {
        throw new \Exception('Not a valid export format');
    }
}
