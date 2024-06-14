<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Converter;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use HK47196\OIDCProvider\Entity\Scope as ScopeEntity;
use HK47196\OIDCProvider\ValueObject\Scope as ScopeModel;

final class ScopeConverter implements ScopeConverterInterface
{
	public function toDomain(ScopeEntityInterface $scope): ScopeModel
	{
		return new ScopeModel($scope->getIdentifier());
	}

	/**
	 * @param list<ScopeEntityInterface> $scopes
	 *
	 * @return list<ScopeModel>
	 */
	public function toDomainArray(array $scopes): array
	{
		return array_map(function (ScopeEntityInterface $scope): ScopeModel {
			return $this->toDomain($scope);
		}, $scopes);
	}

	public function toLeague(ScopeModel $scope): ScopeEntity
	{
		$scopeEntity = new ScopeEntity();
		$scopeEntity->setIdentifier((string)$scope);

		return $scopeEntity;
	}

	/**
	 * @param list<ScopeModel> $scopes
	 *
	 * @return list<ScopeEntity>
	 */
	public function toLeagueArray(array $scopes): array
	{
		return array_map(function (ScopeModel $scope): ScopeEntity {
			return $this->toLeague($scope);
		}, $scopes);
	}
}