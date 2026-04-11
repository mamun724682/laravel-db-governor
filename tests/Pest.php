<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mamun724682\DbGovernor\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

