<?php

declare(strict_types=1);

namespace Modules\Core\App\Helpers;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use UnexpectedValueException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ResponseBuilder
{
    // protected mixed $data = null;

    protected int $status = Response::HTTP_OK;

    protected mixed $error = null;

    protected ?int $totalRecords = null;

    protected ?int $currentRecords = null;

    protected ?int $currentPage = null;

    protected ?int $totalPages = null;

    protected ?int $pagination = null;

    protected ?int $from = null;

    protected ?int $to = null;

    protected ?string $class = null;

    protected ?string $table = null;

    protected bool $preview = false;

    /**
     * @var array<string, string>
     */
    protected array $headers = [];

    protected ?Carbon $cachedAt = null;

    private ResourceCollection | JsonResource $resourceResponse;

    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function getHttpErrorStatus(int|string $errorCode): int
    {
        $http_statuses = @array_flip(static::getHttpStatuses());

        return $errorCode !== 0 && is_int($errorCode) && isset($http_statuses[$errorCode]) ? $errorCode : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * @return (int|string)[]
     *
     * @psalm-return array<array-key>
     */
    protected static function getHttpStatuses(): array
    {
        $https_statuses = Response::$statusTexts;
        $https_statuses[419] = 'Session expired';

        return array_flip($https_statuses);
    }

    /**
     * Set the value of data.
     *
     *
     */
    public function setData(mixed $data): static
    {
        if ($data instanceof JsonResource) {
            $this->resourceResponse = $data;
            $realData = $this->resourceResponse->resource;

            if ($realData && ($realData instanceof Collection || Arr::isList($realData))) {
                $this->setClass($data instanceof Collection ? $data->first() : $data[0]);
            } elseif (is_object($data)) {
                $this->setClass($data);
            }
        } elseif ($data instanceof Collection || Arr::isList($data)) {
            $this->resourceResponse = new ResourceCollection($data);

            if (!empty($data)) {
                $this->setClass($data instanceof Collection ? $data->first() : $data[0]);
            }
        } elseif ($data instanceof Throwable) {
            report($data);
            $this->resourceResponse = new JsonResource(null);
            $this->setError($data);
            $this->setClass($data);
            $this->setStatus(static::getHttpErrorStatus($data->getCode()));
        } else {
            $this->resourceResponse = new JsonResource($data);

            if (is_object($data)) {
                $this->setClass($data);
            }
        }

        return $this;
    }

    /**
     * Set the value of status.
     */
    public function setStatus(int $status): static
    {
        if (!in_array($status, static::getHttpStatuses(), true)) {
            throw new UnexpectedValueException("{$status} is not a valid status");
        }
        $this->status = $status;

        return $this;
    }

    /**
     * Set the value of error.
     *
     * @param  null|string|string[]|Throwable  $error
     */
    public function setError(string|array|null|Throwable $error): static
    {
        $this->error = $error;

        if (!$this->error) {
            $this->setStatus(Response::HTTP_OK);
        } elseif ($this->error instanceof Throwable) {
            $this->setStatus(static::getHttpErrorStatus($this->error->getCode()));
        } elseif (is_array($this->error)) {
            $this->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        } else {
            $this->setStatus(static::getHttpErrorStatus($this->error));
        }

        return $this;
    }

    /**
     * Set the value of totalRecords.
     */
    public function setTotalRecords(?int $totalRecords): static
    {
        $this->totalRecords = $totalRecords;

        return $this;
    }

    /**
     * Set the value of pageRecords.
     */
    public function setCurrentRecords(?int $currentRecords): static
    {
        $this->currentRecords = $currentRecords;

        return $this;
    }

    /**
     * Set the value of currentPage.
     */
    public function setCurrentPage(?int $currentPage): static
    {
        $this->currentPage = $currentPage;

        return $this;
    }

    /**
     * Set the value of totalPages.
     */
    public function setTotalPages(?int $totalPages): static
    {
        $this->totalPages = $totalPages;

        return $this;
    }

    /**
     * Set the value of pagination.
     */
    public function setPagination(?int $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    /**
     * Set the value of from.
     */
    public function setFrom(?int $from): static
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set the value of to.
     */
    public function setTo(?int $to): static
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Set the value of class.
     *
     * @param  null|object|class-string  $class
     */
    public function setClass(object|string|null $class): static
    {
        $this->class = is_object($class) ? get_class($class) : $class;

        if ($class instanceof Model) {
            $this->table = $class->getTable();
        }

        return $this;
    }

    /**
     * Set the value of table.
     */
    public function setTable(?string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Set the value of headers.
     *
     * @param  array<string, string>  $headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function setHeader(string $header, ?string $value): static
    {
        if ($value !== null) {
            $this->headers[$header] = $value;
        } else {
            unset($this->headers[$header]);
        }

        return $this;
    }

    /**
     * Set the value of cachedAt.
     */
    public function setCachedAt(?Carbon $cachedAt = null): static
    {
        $this->cachedAt = $cachedAt ?? new Carbon();

        return $this;
    }

    public function data(): mixed
    {
        return $this->resourceResponse->resource;
    }

    /**
     * @return array{data: mixed, meta: array{status: int, preview: bool, totalRecords?: int, currentRecords?: int, currentPage?: int, totalPages?: int, pagination?: int, from?: int, to?: int|mixed, class?: string, table?: string, cachedAt?: Carbon}, error?: mixed|string, exception?: array{code: int|string, file: string, line: int, trace: list<array{args?: array, class?: class-string, file?: string, function?: string, line?: int, type?: '->'|'::'}>}}
     */
    public function toArray(): array
    {
        $payload = [
            'meta' => [
                'status' => $this->status,
                'preview' => preview(),
            ],
        ];

        if (isset($this->error)) {
            if ($this->error instanceof \Exception) {
                $payload['error'] = $this->error->getMessage();

                if (config('app.debug') == true) {
                    $payload['exception'] = [
                        'code' => $this->error->getCode(),
                        'file' => $this->error->getFile(),
                        'line' => $this->error->getLine(),
                        'trace' => $this->error->getTrace(),
                    ];
                }
            } else {
                $payload['error'] = $this->error;
            }
        }

        if (!$this->resourceResponse) {
            $this->resourceResponse = new JsonResource(null);
        } elseif ($this->resourceResponse instanceof ResourceCollection) {
            if (isset($this->totalRecords)) {
                $payload['meta']['totalRecords'] = $this->totalRecords;
            }

            if (isset($this->currentRecords)) {
                $payload['meta']['currentRecords'] = $this->currentRecords;
            }

            if (isset($this->currentPage)) {
                $payload['meta']['currentPage'] = $this->currentPage;
            }

            if (isset($this->totalPages)) {
                $payload['meta']['totalPages'] = $this->totalPages;
            }

            if (isset($this->pagination)) {
                $payload['meta']['pagination'] = $this->pagination;
            }

            if (isset($this->from)) {
                $payload['meta']['from'] = $this->from;

                if (isset($this->to)) {
                    $count = $this->data()->count();
                    $payload['meta']['to'] = $count < $this->to - $this->from + 1 ? $this->from + $count - 1 : $this->to;
                }
            }
        }

        if (isset($this->cachedAt)) {
            $payload['meta']['cachedAt'] = $this->cachedAt;
        }

        if (config('app.debug')) {
            if (isset($this->class)) {
                $payload['meta']['class'] = $this->class;
            }

            if (isset($this->table)) {
                $payload['meta']['table'] = $this->table;
            }
            $route = request()->route();
            $payload['meta']['controller'] = $route->getControllerClass();
            $payload['meta']['action'] = $route->getActionMethod();
        }

        $this->resourceResponse->with = $payload;

        return $this->resourceResponse->toArray($this->request);
    }

    public function json(): Response
    {
        return response()->json($this->toArray(), $this->status, $this->headers);
    }
}
