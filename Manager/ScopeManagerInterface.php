<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager;


use HK47196\OIDCProvider\ValueObject\Scope;

interface ScopeManagerInterface
{
	public function find(string $identifier): ?Scope;

	public function save(Scope $scope): void;
}