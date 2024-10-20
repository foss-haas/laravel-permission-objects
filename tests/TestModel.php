<?php

namespace FossHaas\LaravelPermissionObjects\Tests;

class TestModel
{
  public function __construct(public array $attributes = []) {}
  public function getKey()
  {
    return $this->attributes['id'] ?? null;
  }
}
