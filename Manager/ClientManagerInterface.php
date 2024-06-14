<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager;

use HK47196\OIDCProvider\Model\ClientInterface;

interface ClientManagerInterface
{
	public function save(ClientInterface $client): void;

	public function remove(ClientInterface $client): void;

	public function find(string $identifier): ?ClientInterface;

	/**
	 * @return list<ClientInterface>
	 */
	public function list(): array;
}
