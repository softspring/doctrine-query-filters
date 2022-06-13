<?php

namespace Softspring\Component\DoctrineQueryFilters;

use Doctrine\ORM\QueryBuilder;
use Softspring\Component\DoctrineQueryFilters\Exception\InvalidFilterValueException;
use Softspring\Component\DoctrineQueryFilters\Exception\InvalidFilterFormException;
use Softspring\Component\DoctrineQueryFilters\Exception\MissingFromInQueryBuilderException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class Filters
{
    /**
     * @throws InvalidFilterFormException
     * @throws InvalidFilterValueException
     * @throws MissingFromInQueryBuilderException
     */
    public static function applyForm(QueryBuilder $qb, FormInterface $filterForm, ?Request $request = null): QueryBuilder
    {
        $reflectionClass = new \ReflectionClass($filterForm->getConfig()->getType()->getInnerType());
        if (!$reflectionClass->implementsInterface(FilterFormInterface::class)) {
            throw new InvalidFilterFormException();
        }

        $request && $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            self::apply($qb, $filterForm->getData());
        }

        return $qb;
    }

    /**
     * @throws InvalidFilterValueException
     * @throws MissingFromInQueryBuilderException
     */
    public static function apply(QueryBuilder $qb, array $filters): QueryBuilder
    {
        foreach ($filters as $filterName => $value) {
            self::filterField($qb, $filterName, $value);
        }

        return $qb;
    }

    /**
     * @throws InvalidFilterValueException
     * @throws MissingFromInQueryBuilderException
     */
    protected static function filterField(QueryBuilder $qb, string $field, $value): void
    {
        [$fieldName, $operatorName] = self::splitFieldName($field);
        $entityAliases = $qb->getAllAliases();

        if (empty($entityAliases)) {
            throw new MissingFromInQueryBuilderException();
        }

        $entityAlias = $entityAliases[0];

        $fieldParameter = 'f'.substr(md5($field), 0, 5);

        switch ($operatorName) {
            case 'like':
                $operator = 'LIKE';
                $value = "%$value%";
                break;

            case 'in':
                $qb->andWhere($qb->expr()->in(sprintf('%s.%s', $entityAlias, $fieldName), is_array($value) ? $value : [$value]));
                return;

            case 'between':
                $value0 = $value[0];
                $value1 = $value[1];
                $value0 = $value0 instanceof \DateTime ? $value0->format('Y-m-d') : $value0;
                $value1 = $value1 instanceof \DateTime ? $value1->format('Y-m-d') : $value1;

                // add quotes
                $value0 = is_numeric($value0) ? $value0 : "\"$value0\"";
                $value1 = is_numeric($value1) ? $value1 : "\"$value1\"";

                $qb->andWhere($qb->expr()->between(sprintf('%s.%s', $entityAlias, $fieldName), $value0, $value1));
                return;

            case 'lt':
            case 'lte':
            case 'gt':
            case 'gte':
                $value = $value instanceof \DateTime ? "\"".$value->format('Y-m-d')."\"" : $value;
                $qb->andWhere($qb->expr()->$operatorName(sprintf('%s.%s', $entityAlias, $fieldName), $value));
                return;

            case 'null':
                if ($value) {
                    $qb->andWhere(sprintf('%s.%s IS NULL', $entityAlias, $fieldName));
                } else {
                    $qb->andWhere(sprintf('%s.%s IS NOT NULL', $entityAlias, $fieldName));
                }
                return;

            case 'is':
                if ($value === null || $value === 'null') {
                    $qb->andWhere(sprintf('%s.%s IS NULL', $entityAlias, $fieldName));
                } elseif ($value === 'not_null') {
                    $qb->andWhere(sprintf('%s.%s IS NOT NULL', $entityAlias, $fieldName));
                } else {
                    throw new InvalidFilterValueException('Invalid is filter, must be "null", null or "not_null", no other case is yet implemented');
                }
                return;

            default:
                $fieldName = $field;
                $operator = '=';
        }

        $qb->andWhere(sprintf('%s.%s %s :%s', $entityAlias, $fieldName, $operator, $fieldParameter));
        $qb->setParameter($fieldParameter, $value);
    }

    private static function splitFieldName(string $field): array
    {
        $parts = explode('__', $field);

        if (sizeof($parts) == 1) {
            return [$parts[0], null];
        }

        if (sizeof($parts) == 2) {
            return $parts;
        }

        $operator = array_pop($parts);

        return [implode('__', $parts), $operator];
    }
}