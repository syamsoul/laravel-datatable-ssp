<?php
namespace SoulDoit\DataTable\Handler;

use SoulDoit\DataTable\SSP;

class Handler
{
    private $frontend_handler = null;
    private $columns_handler = null;
    private $query_handler = null;
    private $data_handler = null;

    public function __construct(
        public SSP $ssp
    ) {}

    public function columns(): ColumnsHandler
    {
        if ($this->columns_handler === null) {
            $this->columns_handler = new ColumnsHandler($this);
        }

        return $this->columns_handler;
    }

    public function query(): QueryHandler
    {
        if ($this->query_handler === null) {
            $this->query_handler = new QueryHandler($this);
        }

        return $this->query_handler;
    }

    public function data(): DataHandler
    {
        if ($this->data_handler === null) {
            $this->data_handler = new DataHandler($this);
        }

        return $this->data_handler;
    }

    public function frontend(): FrontendHandler
    {
        if ($this->frontend_handler === null) {
            $this->frontend_handler = new FrontendHandler($this);
        }

        return $this->frontend_handler;
    }

    public function response(): ResponseHandler
    {
        return new ResponseHandler($this);
    }
}
