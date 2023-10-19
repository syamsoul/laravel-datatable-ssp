# Changelog

All notable changes to `laravel-datatable-ssp` will be documented in this file

## 3.2.5 - 2023-10-19
- New feature: Can disable fetch on init (only applied to `others` framework) on frontend (e.g: `$ssp->frontend()->disableFetchOnInit()`)

## 3.2.4 - 2023-10-19
- Refactoring (separate into a few Handler)
- New feature: Set frontend settings using chaining method (e.g: `$ssp->frontend()->setResponseDataRouteName('users.data')->setInitialSorting('created_at', true)`)
- New feature: Get frontend settings via only a single method (e.g: `$ssp->frontend()->getSettings()`)
- Remove feature: `SSP->setFrontEndFramework()`
- Remove feature: `SSP->getFrontEndColumns()`
- Remove feature: `SSP->getFrontEndInitialSorting()`

## 3.1.0 - 2023-10-14
- New feature: `SSP->getFrontEndInitialSorting()`

## 3.0.0 - 2023-10-14
- Initial release of version 3

## 2.1.1 - 2019-05-26
- Debug: `searchKeywordFormatter`

## 2.1.0 - 2019-05-26
- Debug: handle `db` that use `DB::raw()`
- New feature: `searchKeywordFormatter` for formatting the keyword that will appear in table 

## 2.0.3 - 2019-05-04
- Improvement: alias table name for leftjoin
- Improvement: can also put table's name for first parameter in constructor

## 2.0.2 - 2019-05-03
- Debug: custom leftjoin column's name to avoid conflict if leftjoin table has same column name with main table 

## 2.0.0 - 2019-05-03
- `formatter` will return two parameters which are `$value` and `$model`
- New feature: `leftJoin`
- First parameter in `SSP` constructor can receive model string or table's name string.

## 1.0.0 - 2019-03-21
- Everything, initial release
