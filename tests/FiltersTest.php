<?php

namespace Softspring\Component\DoctrineQueryFilters\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Softspring\Component\DoctrineQueryFilters\Exception\InvalidFilterFormException;
use Softspring\Component\DoctrineQueryFilters\Exception\InvalidFilterValueException;
use Softspring\Component\DoctrineQueryFilters\Exception\MissingFromInQueryBuilderException;
use Softspring\Component\DoctrineQueryFilters\Filters;
use Symfony\Component\Form\Test\TypeTestCase;

class FiltersTest extends TypeTestCase
{
    public function collectionProvider(): array
    {
        return [
            [['name__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.name LIKE "%test%"'],
            [['name__like___or___surname__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.name LIKE "%test%" OR t.surname LIKE "%test%"'],
            [['owner.name__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t LEFT JOIN t.owner owner WHERE owner.name LIKE "%test%"'],
            [['owner.name__like___or___owner.surname__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t LEFT JOIN t.owner owner WHERE owner.name LIKE "%test%" OR owner.surname LIKE "%test%"'],
            [['name__like' => 'test', 'owner.name__like___or___owner.surname__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t LEFT JOIN t.owner owner WHERE t.name LIKE "%test%" AND (owner.name LIKE "%test%" OR owner.surname LIKE "%test%")'],
            [['name__like' => 'test', 'owner.name__like___or___owner.surname__like' => 'test'], [], Filters::MODE_OR, 'SELECT t FROM test t LEFT JOIN t.owner owner WHERE t.name LIKE "%test%" OR (owner.name LIKE "%test%" OR owner.surname LIKE "%test%")'],
            [['status__in' => ['1', '2']], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.status IN(\'1\', \'2\')'],
            [['field__null' => true], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.field IS NULL'],
            [['field__null' => false], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.field IS NOT NULL'],
            [['age__lt' => 1], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age < 1'],
            [['age__lte' => 1], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age <= 1'],
            [['age__gt' => 2], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age > 2'],
            [['age__gte' => 2], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age >= 2'],
            [['age__is' => null], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age IS NULL'],
            [['age__is' => 'null'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age IS NULL'],
            [['age__is' => 'not_null'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.age IS NOT NULL'],
            [['field_with_underscores__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.field_with_underscores LIKE "%test%"'],
            [['raw' => 'value'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.raw = "value"'],
            [['raw_with_underscores' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.raw_with_underscores = "test"'],
            [['name__like' => 'test', 'age__lt' => 1], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.name LIKE "%test%" AND t.age < 1'],
            [['date__between' => ['01-01-1900', '01-01-2000']], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.date BETWEEN "01-01-1900" AND "01-01-2000"'],
            [['number__between' => [50, 100]], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.number BETWEEN 50 AND 100'],
            [['other__file__name__' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t.other__file__name__ = "test"'],
            [['children->field__like' => 'test'], [], Filters::MODE_AND, 'SELECT t FROM test t WHERE t IN(SELECT IDENTITY(ch.parent) FROM test t WHERE ch.parent = t.id AND t.field LIKE "%test%")', [
                'children' => [
                    'targetEntity' => 'TestClass',
                    'mappedBy' => 'parent',
                ],
            ]],
            [[], ['field' => 'asc'], Filters::MODE_AND, 'SELECT t FROM test t ORDER BY t.field asc'],
        ];
    }

    /**
     * @dataProvider collectionProvider
     */
    public function testAddPaginationFirstPage(array $filters, array $sortBy, int $mode, string $expectedDql, array $associationMapping = []): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));


        $em->method('getRepository')->willReturnCallback(function ($className) use ($em) {
            $repository = $this->createMock(EntityRepository::class);
            $repository->method('createQueryBuilder')->willReturn($sqb = new QueryBuilder($em));
            $sqb->from('test', 't');

            return $repository;
        });

        $classMetadataMock = $this->createMock(ClassMetadata::class);
        $classMetadataMock->method('getAssociationMapping')->willReturnCallback(function($fieldName) use ($associationMapping) {
            return $associationMapping[$fieldName] ?? null;
        });
        $classMetadataMock->method('getSingleIdentifierFieldName')->willReturn('id');

        $em->method('getClassMetadata')->willReturn($classMetadataMock);

        $qb = Filters::apply($em->createQueryBuilder()->select('t')->from('test', 't'), $filters, $mode);

        if (!empty($sortBy)) {
            Filters::sortBy($qb, $sortBy);
        }

        // replace parameters to check DQL query
        $params = $qb->getParameters();
        $dql = $qb->getDQL();
        /** @var Parameter $param */
        foreach ($params as $param) {
            $dql = str_ireplace(':'.$param->getName(), '"'.$param->getValue().'"', $dql);
        }
        $this->assertEquals($expectedDql, $dql);
    }

    public function testMissingFromException()
    {
        $this->expectException(MissingFromInQueryBuilderException::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));

        Filters::apply($em->createQueryBuilder(), ['test' => true]);
    }

    public function testInvalidFilterValueException()
    {
        $this->expectException(InvalidFilterValueException::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));

        Filters::apply($em->createQueryBuilder()->select('t')->from('test', 't'), ['test__is' => 'failed']);
    }

//    public function testFilterForm()
//    {
//        $form = $this->factory->create(ExampleFilterForm::class);
//
//        $form->submit([
//            'example' => 'john',
//        ]);
//
//        $em = $this->createMock(EntityManagerInterface::class);
//        $em->method('getExpressionBuilder')->willReturn(new Expr());
//        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));
//
//        $qb = $em->createQueryBuilder()->select('t')->from('test', 't');
//
//        $qb = Filters::applyForm($qb, $form);
//
//        $params = $qb->getParameters();
//        $dql = $qb->getDQL();
//        /** @var Parameter $param */
//        foreach ($params as $param) {
//            $dql = str_ireplace(':'.$param->getName(), '"'.$param->getValue().'"', $dql);
//        }
//
//        $this->assertEquals('SELECT t FROM test t WHERE t.example LIKE "%john%"', $dql);
//
//        $options = $form->getConfig()->getOptions();
//
//        $this->assertFalse($options['csrf_protection']);
//        $this->assertFalse($options['required']);
//        $this->assertTrue($options['allow_extra_fields']);
//        $this->assertEquals('GET', $options['method']);
//    }
//
//    public function testInvalidFilterForm()
//    {
//        $this->expectException(InvalidFilterFormException::class);
//
//        $form = $this->factory->create(InvalidFilterForm::class);
//
//        $em = $this->createMock(EntityManagerInterface::class);
//        $em->method('getExpressionBuilder')->willReturn(new Expr());
//        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));
//
//        $qb = $em->createQueryBuilder()->select('t')->from('test', 't');
//        Filters::applyForm($qb, $form);
//    }
}
