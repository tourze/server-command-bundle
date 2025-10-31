<?php

namespace ServerCommandBundle\Contracts;

interface ProgressModel
{
    public function setProgress(?int $progress): void;

    public function appendLog(string $log): void;
}
