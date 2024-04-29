<?php

namespace Modules\Core\Grids\Resources;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ViewExport implements FromView
{
    private array $headers = [];

    private array $data = [];

    public function __construct(array $headers, array $data)
    {
        $this->headers = $headers;
        $this->data = $data;
    }

    /** @psalm-suppress InvalidReturnType */
    public function view(): View
    {
        /** @psalm-suppress InvalidReturnStatement */
        return view('exports.table', ['headers' => $this->headers, 'data' => $this->data]);
    }
}
