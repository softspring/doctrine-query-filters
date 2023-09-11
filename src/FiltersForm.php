<?php

namespace Softspring\Component\DoctrineQueryFilters;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FiltersForm extends AbstractType implements FilterFormInterface
{
    protected ?EntityManagerInterface $em = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    final public function getBlockPrefix(): string
    {
        return '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'required' => false,
            'attr' => ['novalidate' => 'novalidate'],
            'allow_extra_fields' => true,
            'query_builder_mode' => Filters::MODE_AND,
            'em' => $this->em,
            'class' => null,
            'query_builder' => null,
        ]);

        $resolver->setRequired('class');
        $resolver->addAllowedTypes('class', ['string']);
        $resolver->addAllowedTypes('query_builder', ['null', 'callable', QueryBuilder::class]);

        $resolver->setNormalizer('query_builder', function (Options $options, $queryBuilder) {
            if ($queryBuilder instanceof QueryBuilder) {
                return $queryBuilder;
            }

            if (\is_callable($queryBuilder)) {
                $queryBuilder = $queryBuilder($options['em']->getRepository($options['class']));

                if (null !== $queryBuilder && !$queryBuilder instanceof QueryBuilder) {
                    throw new UnexpectedTypeException($queryBuilder, QueryBuilder::class);
                }

                return $queryBuilder;
            }

            $reflectionClass = new \ReflectionClass($options['class']);
            $entityAlias = strtolower(substr($reflectionClass->getShortName(), 0, 1));

            return $options['em']->getRepository($options['class'])->createQueryBuilder($entityAlias);
        });
    }
}
