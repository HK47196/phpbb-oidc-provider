<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager\Memory;

use HK47196\OIDCProvider\Manager\ScopeManagerInterface;
use HK47196\OIDCProvider\ValueObject\Scope;
use Symfony\Component\Yaml\Yaml;

class ScopeManager implements ScopeManagerInterface
{
	/** @var list<Scope> */
	private array $scopes;

	public function __construct()
	{
		$this->scopes = self::load();
	}

	public function find(string $identifier): ?Scope
	{
		foreach ($this->scopes as $scope) {
			if ((string)$scope === $identifier) {
				return $scope;
			}
		}
		return null;
	}

	public function save(Scope $scope): void
	{
		throw new \RuntimeException('Not implemented');
	}

	/**
	 * @return list<Scope>
	 */
	private static function load(): array
	{
		$confPath = __DIR__ . '/../../config/scopes.yml';
		$conf = Yaml::parse(file_get_contents($confPath));
		$config = $conf['scopes'];
		$scopes = [];
		foreach ($config as $scope) {
			$scopes[] = new Scope($scope);
		}
		return $scopes;
	}
}