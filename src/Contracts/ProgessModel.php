<?php

namespace ServerCommandBundle\Contracts;

interface ProgessModel
{
    public function appendLog(string $log): static;
}
