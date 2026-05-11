# Doctrine Query Filters Features

Functional definition for `softspring/doctrine-query-filters`.

This file defines the expected behavior and scope of the component.

## Purpose

- Apply reusable filter logic to Doctrine query builders without rewriting the same conditions in every repository or admin screen.
- Provide a small bridge between Symfony filter forms and Doctrine query filtering.

## Main Features

- Apply filters to a Doctrine `QueryBuilder` from a simple associative array.
- Support operators such as equality, `like`, `ilike`, `in`, `notIn`, `between`, comparisons, and null checks.
- Support grouped OR filters through combined field syntax.
- Support `order by` definitions, including joined-property sorting.
- Provide a base `FiltersForm` type for GET-based filter forms.

## Expected Usage

- Use `Filters::apply()` when filters already exist as normalized array data.
- Use `Filters::sortBy()` to apply user-facing sort definitions to a query builder.
- Extend `FiltersForm` when a Symfony form should control filter submission and query builder defaults.
- Keep field naming predictable so operators and joined-field paths remain easy to read.

## Operational Expectations

- Invalid filter values should fail with explicit component exceptions.
- Queries without a root alias should fail with an explicit exception instead of generating broken DQL.
- Joined-field filters and sorts should reuse or create joins automatically when needed.

## Current Limits

- The filter grammar is intentionally string-based and relies on field naming conventions.
- The component helps with query filtering, not with full search architecture.
- The form integration is lightweight and expects the application to define the actual filter form fields.
