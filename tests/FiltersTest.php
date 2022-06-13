<?php

namespace Softspring\Component\DoctrineQueryFilters\Tests;

use Doctrine\ORM\EntityManagerInterface;
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
            [['name__like' => 'test'], 'SELECT t FROM test t WHERE t.name LIKE "%test%"'],
            [['status__in' => ['1', '2']], 'SELECT t FROM test t WHERE t.status IN(\'1\', \'2\')' ],
            [['field__null' => true], 'SELECT t FROM test t WHERE t.field IS NULL'],
            [['field__null' => false], 'SELECT t FROM test t WHERE t.field IS NOT NULL'],
            [['age__lt' => 1], 'SELECT t FROM test t WHERE t.age < 1'],
            [['age__lte' => 1], 'SELECT t FROM test t WHERE t.age <= 1'],
            [['age__gt' => 2], 'SELECT t FROM test t WHERE t.age > 2'],
            [['age__gte' => 2], 'SELECT t FROM test t WHERE t.age >= 2'],
            [['age__is' => null], 'SELECT t FROM test t WHERE t.age IS NULL'],
            [['age__is' => 'null'], 'SELECT t FROM test t WHERE t.age IS NULL'],
            [['age__is' => 'not_null'], 'SELECT t FROM test t WHERE t.age IS NOT NULL'],
            [['field_with_underscores__like' => 'test'], 'SELECT t FROM test t WHERE t.field_with_underscores LIKE "%test%"'],
            [['raw' => 'value'], 'SELECT t FROM test t WHERE t.raw = "value"' ],
            [['raw_with_underscores' => 'test'], 'SELECT t FROM test t WHERE t.raw_with_underscores = "test"'],
            [['name__like' => 'test', 'age__lt' => 1], 'SELECT t FROM test t WHERE t.name LIKE "%test%" AND t.age < 1'],
            [['date__between' => ['01-01-1900', '01-01-2000']], 'SELECT t FROM test t WHERE t.date BETWEEN "01-01-1900" AND "01-01-2000"'],
            [['number__between' => [50, 100]], 'SELECT t FROM test t WHERE t.number BETWEEN 50 AND 100'],
            [['other__file__name__' => 'test'], 'SELECT t FROM test t WHERE t.other__file__name__ = "test"'],
        ];
    }

    /**
     * @dataProvider collectionProvider
     */
    public function testAddPaginationFirstPage(array $filters, string $expectedDql): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));

        $qb = Filters::apply($em->createQueryBuilder()->select('t')->from('test', 't'), $filters);

        // replace parameters to check DQL query
        $params = $qb->getParameters();
        $dql = $qb->getDQL();
        /** @var Parameter $param */
        foreach ($params as $param) {
            $dql = str_ireplace(':'.$param->getName(), '"'. $param->getValue() . '"', $dql);
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

    public function testFilterForm()
    {
        $form = $this->factory->create(ExampleFilterForm::class);

        $form->submit([
            'example' => 'john',
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));

        $qb = $em->createQueryBuilder()->select('t')->from('test', 't');

        $qb = Filters::applyForm($qb, $form);

        $params = $qb->getParameters();
        $dql = $qb->getDQL();
        /** @var Parameter $param */
        foreach ($params as $param) {
            $dql = str_ireplace(':'.$param->getName(), '"'. $param->getValue() . '"', $dql);
        }

        $this->assertEquals('SELECT t FROM test t WHERE t.example LIKE "%john%"', $dql);

        $options = $form->getConfig()->getOptions();

        $this->assertFalse($options['csrf_protection']);
        $this->assertFalse($options['required']);
        $this->assertTrue($options['allow_extra_fields']);
        $this->assertEquals('GET', $options['method']);
    }

    public function testInvalidFilterForm()
    {
        $this->expectException(InvalidFilterFormException::class);

        $form = $this->factory->create(InvalidFilterForm::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturn(new QueryBuilder($em));

        $qb = $em->createQueryBuilder()->select('t')->from('test', 't');
        Filters::applyForm($qb, $form);
    }
}