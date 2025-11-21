<?php

declare(strict_types=1);

namespace APY\DataGridBundle\Grid\Helper;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\SqlOutputWalker;
use RuntimeException;

use function array_diff;
use function array_keys;
use function assert;
use function count;
use function implode;
use function reset;
use function sprintf;

/**
 * Just a copy of Doctrine\ORM\Tools\Pagination\CountOutputWalker which handles both OFFSET and LIMIT.
 *
 * @phpstan-import-type QueryComponent from Parser
 */
class CountOffsetLimitOutputWalker extends SqlOutputWalker
{
    private readonly int $firstResult;
    private readonly int|null $maxResults;
    private readonly AbstractPlatform $platform;
    private readonly ResultSetMapping $rsm;

    /**
     * {@inheritDoc}
     */
    public function __construct(Query $query, ParserResult $parserResult, array $queryComponents)
    {
        $this->firstResult = $query->getFirstResult();
        $this->maxResults  = $query->getMaxResults();
        $this->platform = $query->getEntityManager()->getConnection()->getDatabasePlatform();
        $this->rsm      = $parserResult->getResultSetMapping();

        parent::__construct($query, $parserResult, $queryComponents);
    }

    protected function createSqlForFinalizer(SelectStatement $selectStatement): string
    {
        if ($this->platform instanceof SQLServerPlatform) {
            $selectStatement->orderByClause = null;
        }

        $sql = parent::createSqlForFinalizer($selectStatement);

        if ($selectStatement->groupByClause) {
            return sprintf(
                'SELECT COUNT(*) AS dctrn_count FROM (%s%s%s) dctrn_table',
                $sql,
                !is_null($this->maxResults) && !str_contains($sql, 'LIMIT') ? " LIMIT $this->maxResults" : '',
                $this->firstResult > 0 && !str_contains($sql, 'OFFSET') ? " OFFSET $this->maxResults" : ''
            );
        }

        // Find out the SQL alias of the identifier column of the root entity
        // It may be possible to make this work with multiple root entities but that
        // would probably require issuing multiple queries or doing a UNION SELECT
        // so for now, It's not supported.

        // Get the root entity and alias from the AST fromClause
        $from = $selectStatement->fromClause->identificationVariableDeclarations;
        if (count($from) > 1) {
            throw new RuntimeException('Cannot count query which selects two FROM components, cannot make distinction');
        }

        $fromRoot       = reset($from);
        $rootAlias      = $fromRoot->rangeVariableDeclaration->aliasIdentificationVariable;
        $rootClass      = $this->getMetadataForDqlAlias($rootAlias);
        $rootIdentifier = $rootClass->identifier;

        // For every identifier, find out the SQL alias by combing through the ResultSetMapping
        $sqlIdentifier = [];
        foreach ($rootIdentifier as $property) {
            if (isset($rootClass->fieldMappings[$property])) {
                foreach (array_keys($this->rsm->fieldMappings, $property, true) as $alias) {
                    if ($this->rsm->columnOwnerMap[$alias] === $rootAlias) {
                        $sqlIdentifier[$property] = $alias;
                    }
                }
            }

            if (isset($rootClass->associationMappings[$property])) {
                $association = $rootClass->associationMappings[$property];
                assert($association->isToOneOwningSide());
                $joinColumn = $association->joinColumns[0]->name;

                foreach (array_keys($this->rsm->metaMappings, $joinColumn, true) as $alias) {
                    if ($this->rsm->columnOwnerMap[$alias] === $rootAlias) {
                        $sqlIdentifier[$property] = $alias;
                    }
                }
            }
        }

        if (count($rootIdentifier) !== count($sqlIdentifier)) {
            throw new RuntimeException(sprintf(
                'Not all identifier properties can be found in the ResultSetMapping: %s',
                implode(', ', array_diff($rootIdentifier, array_keys($sqlIdentifier))),
            ));
        }

        // Build the counter query
        return sprintf(
            'SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT %s FROM (%s%s%s) dctrn_result) dctrn_table',
            implode(', ', $sqlIdentifier),
            $sql,
            !is_null($this->maxResults) && !str_contains($sql, 'LIMIT') ? " LIMIT $this->maxResults" : '',
            $this->firstResult > 0 && !str_contains($sql, 'OFFSET') ? " OFFSET $this->maxResults" : ''
        );
    }
}
