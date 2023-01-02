<?php

namespace Softspring\Component\DoctrineQueryFilters;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FiltersForm extends AbstractType implements FilterFormInterface
{
    final public function getBlockPrefix(): string
    {
        return '';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'required' => false,
            'attr' => ['novalidate' => 'novalidate'],
            'allow_extra_fields' => true,
            'query_builder_mode' => Filters::MODE_AND,
        ]);
    }
}
