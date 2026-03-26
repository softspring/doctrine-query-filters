<?php

namespace Softspring\Component\DoctrineQueryFilters\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Softspring\Component\DoctrineQueryFilters\Filters;
use stdClass;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AllowMockObjectsWithoutExpectations]
class FiltersFormTest extends TestCase
{
    public function testDefaultsUseGetMethodAndCreateDefaultQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('createQueryBuilder')->with('s')->willReturn($queryBuilder);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('getRepository')->with(stdClass::class)->willReturn($repository);

        $formType = new ExampleFilterForm($em);
        $resolver = new OptionsResolver();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve([
            'class' => stdClass::class,
        ]);

        self::assertSame('', $formType->getBlockPrefix());
        self::assertFalse($options['csrf_protection']);
        self::assertFalse($options['required']);
        self::assertTrue($options['allow_extra_fields']);
        self::assertSame('GET', $options['method']);
        self::assertSame(Filters::MODE_AND, $options['query_builder_mode']);
        self::assertSame($queryBuilder, $options['query_builder']);
    }

    public function testCallableQueryBuilderIsResolved(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $repository = $this->createMock(EntityRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('getRepository')->with(stdClass::class)->willReturn($repository);

        $formType = new ExampleFilterForm($em);
        $resolver = new OptionsResolver();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve([
            'class' => stdClass::class,
            'query_builder' => static fn (EntityRepository $repo): QueryBuilder => $queryBuilder,
        ]);

        self::assertSame($queryBuilder, $options['query_builder']);
    }

    public function testCallableMustReturnQueryBuilderOrNull(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('getRepository')->with(stdClass::class)->willReturn($repository);

        $formType = new ExampleFilterForm($em);
        $resolver = new OptionsResolver();
        $formType->configureOptions($resolver);

        $this->expectException(UnexpectedTypeException::class);

        $resolver->resolve([
            'class' => stdClass::class,
            'query_builder' => static fn (): string => 'invalid',
        ]);
    }
}
