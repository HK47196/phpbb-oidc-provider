<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Converter;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use HK47196\OIDCProvider\Entity\Scope as ScopeEntity;
use HK47196\OIDCProvider\ValueObject\Scope as ScopeModel;

interface ScopeConverterInterface
{
	public function toDomain(ScopeEntityInterface $scope): ScopeModel;

	/**
	 * @param list<ScopeEntityInterface> $scopes
	 *
	 * @return list<ScopeModel>
	 */
	public function toDomainArray(array $scopes): array;

	public function toLeague(ScopeModel $scope): ScopeEntity;

	/**
	 * @param list<ScopeModel> $scopes
	 *
	 * @return list<ScopeEntity>
	 */
	public function toLeagueArray(array $scopes): array;
}