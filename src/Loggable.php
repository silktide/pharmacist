<?php


namespace Silktide\SyringeVerifier;


trait Loggable
{
    public function success($message)
    {
        $this->log($message, "\033[32m");
    }

    public function log($message, $color="")
    {
        echo $color.$message."\033[0m\n";
    }

    public function error($message)
    {
        $this->log($message, "\033[31m");
    }
}