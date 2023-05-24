<?php

namespace Softspring\Component\DoctrineQueryFilters;

use Doctrine\ORM\QueryBuilder;
use Softspring\Component\DoctrineQueryFilters\Exception\InvalidFilterFormException;
use Softspring\Component\DoctrineQueryFilters\Exception\InvalidFilterValueException;
use Softspring\Component\DoctrineQueryFilters\Exception\MissingFromInQueryBuilderException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class Filters
{
    public const MODE_AND = 1;

    public const MODE_OR = 2;

//    /**
//     * @throws InvalidFilterFormException
//     * @throws InvalidFilterValueException
//     * @throws MissingFromInQueryBuilderException
//     */
//    public static function applyForm(QueryBuilder $qb, FormInterface $filterForm, ?Request $request = null): QueryBuilder
//    {
//        $reflectionClass = new \ReflectionClass($filterForm->getConfig()->getType()->getInnerType());
//        if (!$reflectionClass->implementsInterface(FilterFormInterface::class)) {
//            throw new InvalidFilterFormException();
//        }
//
//        $request && $filterForm->handleRequest($request);
//
//        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
//            $mode = $filterForm->getConfig()->getOption('query_builder_mode', self::MODE_AND);
//            self::apply($qb, $filterForm->getData(), $mode);
//        }
//
//        return $qb;
//    }

    /**
     * @throws InvalidFilterValueException
     * @throws MissingFromInQueryBuilderException
     */
    public static function apply(QueryBuilder $qb, array $filters, int $mode = self::MODE_AND): QueryBuilder
    {
        foreach ($filters as $filterName => $value) {
            $filters = self::splitOrFields($filterName);
            $filtersExpression = $qb->expr()->orX();

            foreach ($filters as $filter) {
                $filtersExpression->add(self::buildFieldExpression($qb, $filter, $value));
            }

            self::MODE_OR === $mode ? $qb->orWhere($filtersExpression) : $qb->andWhere($filtersExpression);
        }

        return $qb;
    }

    public static function sortBy(QueryBuilder $qb, array $orderBy): QueryBuilder
    {
        $entityAliases = $qb->getAllAliases();
        $entityAlias = $entityAliases[0];

        foreach ($orderBy as $fieldName => $order) {
            if (str_contains($fieldName, '.')) {
                $fieldNameParts = explode('.', $fieldName);
                $fieldName = $fieldNameParts[1];
                $entityAlias = self::joinEntityAlias($qb, $entityAlias, $fieldNameParts[0]);
            }

            $qb->addOrderBy("$entityAlias.$fieldName", $order);
        }

        return $qb;
    }

    protected static function joinEntityAlias(QueryBuilder $qb, string $entityAlias, string $fieldName): string
    {
        $joins = $qb->getDQLPart('join'); // [0]=> { from: class_name, alias: 'x', indexBy = null }

        $joinFieldName = sprintf('%s.%s', $entityAlias, $fieldName);

        $joinDefined = false;
        $fieldAlias = $fieldName;
        foreach ($joins as $a => $join) {
            if ($join[0]->getJoin() == $joinFieldName) {
                $joinDefined = true;
                $fieldAlias = $join[0]->getAlias();
            }
        }

        if (!$joinDefined) {
            $qb->leftJoin($joinFieldName, $fieldAlias);
        }

        return $fieldAlias;
    }

    /**
     * @throws InvalidFilterValueException
     * @throws MissingFromInQueryBuilderException
     */
    protected static function buildFieldExpression(QueryBuilder $qb, string $field, $value)
    {
        [$fieldName, $operatorName] = self::splitFieldName($field);
        $entityAliases = $qb->getAllAliases();

        if (empty($entityAliases)) {
            throw new MissingFromInQueryBuilderException();
        }

        $entityAlias = $entityAliases[0];

        if (str_contains($fieldName, '.')) {
            $fieldNameParts = explode('.', $fieldName);
            $fieldName = $fieldNameParts[1];
            $entityAlias = self::joinEntityAlias($qb, $entityAlias, $fieldNameParts[0]);
        }

        $fieldParameter = 'f'.substr(md5($field), 0, 5);

        switch ($operatorName) {
            case 'like':
                $operator = 'LIKE';
                $value = "%$value%";
                break;

            case 'in':
                return $qb->expr()->in(sprintf('%s.%s', $entityAlias, $fieldName), is_array($value) ? $value : [$value]);

            case 'between':
                $value0 = $value[0];
                $value1 = $value[1];
                $value0 = $value0 instanceof \DateTime ? $value0->format('Y-m-d') : $value0;
                $value1 = $value1 instanceof \DateTime ? $value1->format('Y-m-d') : $value1;

                // add quotes
                $value0 = is_numeric($value0) ? $value0 : "\"$value0\"";
                $value1 = is_numeric($value1) ? $value1 : "\"$value1\"";

                return $qb->expr()->between(sprintf('%s.%s', $entityAlias, $fieldName), $value0, $value1);

            case 'lt':
            case 'lte':
            case 'gt':
            case 'gte':
                $value = $value instanceof \DateTime ? '"'.$value->format('Y-m-d').'"' : $value;

                return $qb->expr()->$operatorName(sprintf('%s.%s', $entityAlias, $fieldName), $value);

            case 'null':
                if ($value) {
                    return sprintf('%s.%s IS NULL', $entityAlias, $fieldName);
                } else {
                    return sprintf('%s.%s IS NOT NULL', $entityAlias, $fieldName);
                }

                // no break
            case 'is':
                if (null === $value || 'null' === $value) {
                    return sprintf('%s.%s IS NULL', $entityAlias, $fieldName);
                } elseif ('not_null' === $value) {
                    return sprintf('%s.%s IS NOT NULL', $entityAlias, $fieldName);
                } else {
                    throw new InvalidFilterValueException('Invalid is filter, must be "null", null or "not_null", no other case is yet implemented');
                }

                // no break
            default:
                if (!isset($fieldNameParts)) {
                    $fieldName = $field;
                }
                $operator = '=';
        }

        $qb->setParameter($fieldParameter, $value);

        return sprintf('%s.%s %s :%s', $entityAlias, $fieldName, $operator, $fieldParameter);
    }

    protected static function splitOrFields(string $fieldName): array
    {
        return explode('___or___', $fieldName);
    }

    private static function splitFieldName(string $field): array
    {
        $parts = explode('__', $field);

        if (1 == sizeof($parts)) {
            return [$parts[0], null];
        }

        if (2 == sizeof($parts)) {
            return $parts;
        }

        $operator = array_pop($parts);

        return [implode('__', $parts), $operator];
    }
}
