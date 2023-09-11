<?php

namespace Softspring\Component\DoctrineQueryFilters\Tests;

use Softspring\Component\DoctrineQueryFilters\FiltersForm;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ExampleFilterForm extends FiltersForm
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('example', TextType::class, [
            'property_path' => '[example__like]',
        ]);
    }
}
