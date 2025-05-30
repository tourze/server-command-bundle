<?php

namespace ServerCommandBundle\Contracts;

interface ProgressModel
{
    public function setProgress(?int $progress): static;

    public function appendLog(string $log): static;
}
