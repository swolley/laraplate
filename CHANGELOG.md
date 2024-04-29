<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

## [2.2.0](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v2.1.0...v2.2.0) (2024-03-13)

### Features


##### Cache

* Creato config per cache_duration ([74b387](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/74b3875bb6973740f1866ccfd487a0aad775c15c))


---

## [2.1.0](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v2.0.0...v2.1.0) (2024-03-13)

### Features


##### Resources

* Modifiche a response builder per utilizzare le Resources ([6d5ebd](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/6d5ebdb9464704fee71094f8999252ce7f8a8379))

##### Traits

* Rinominata classe HasRules in HasValidations ([320051](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/320051afc185625f513fb8f932e4898cd12a8195))


---

## [2.1.0](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v2.0.0...v2.1.0) (2024-03-03)

### Features


##### Traits

* Rinominata classe HasRules in HasValidations ([320051](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/320051afc185625f513fb8f932e4898cd12a8195))


---

## [2.0.1](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v2.0.0...v2.0.1) (2024-02-26)


---

## [2.0.0](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v1.0.0...v2.0.0) (2024-02-25)


---

## [1.0.0](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v0.1.0...v1.0.0) (2024-02-25)


---

## [0.1.0](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/v0.0.1...v0.1.0) (2024-02-25)

### ⚠ BREAKING CHANGES


##### Core

* Creato modulo Core con le funzionalità comuni a tutte le app ([ca8164](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/ca816436434c970ce75aeed51614fbfd4bf3e4f5))
* Refactoring struttura applicazione ([ca8164](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/ca816436434c970ce75aeed51614fbfd4bf3e4f5))


---

## [0.0.1](https://bitbucket.org/willbit_dev/laravel-boilerplate/compare/650fe9a5691b878c75de806fbd6839cd82ad768d...v0.0.1) (2024-02-25)

### ⚠ BREAKING CHANGES


##### Controllers

* New use of preview automatically wired into models ([098543](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/09854357373a70b7200f28e10e659ea92af06960))
* ResponseBuilder + preview + approve/disapprove + relations ([098543](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/09854357373a70b7200f28e10e659ea92af06960))

### Features


##### Acl

* Bozza possibile tabella acl con appunti da ragionare ([a636be](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/a636be5895d20b35c3f73318abc4556ea4c65eb7))

##### Cache

* Aggiunta ricerca in cache delle funzioni di lettura del CrudController ([f65d5b](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/f65d5b45facf7e7ae6f67aa745a18ff83a441078))
* Configurazioni cache redis + revert config activator moduli + bozza repository elasticsearch ([466510](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/46651033c1259b4e4974a90338c48330bc99674d))
* Introduzione redis per cache e sessioni + spostamento griglie in modulo Crud ([ea2f31](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/ea2f310f5b363e47b5449481adc7020fcb25d322))

##### Commands

* Align permissions with Models command ([2d7a78](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/2d7a787e6579e6cf16aa1367de76b48e148f2694))
* Creato comando per controllo translations ([effe00](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/effe00cba758aabeb3ddeef7aa164d589ea8c2f7))
* Make model new propmpts ([405cac](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/405cac6522940c6a5e8c0e80cc764a287ff8b4cf))

##### Controllers

* Crud requests validators ([f77863](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/f77863c2a41d27945ad78a58a28222923eb8e79f))

##### Crud

* Cominciata implementazione tipi colonne in crud requests ([d115a2](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/d115a22920ee89b7f27374096f705bec0785f972))
* Generati oggetti da Request in Crud module ([1b0aa7](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/1b0aa73d98a252ae60c6b54de4bc22fef9f89e9a))
* Generazione query list da request ([099efb](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/099efb73fabf8a416295c424d78c7817b872cbc0))
* Modifiche a recursivelyApplyFilters per generazione query list ([371820](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/371820d4b62c9ede1b17f5fef2fb4a09b9b31dc7))
* Parse requests crud + integrazione phpdoc con psalm ([bb9e3c](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/bb9e3c0f3abe298705a515edbbdbe4ef306b7597))
* Phpdoc + query rotta "list" ([4baddd](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/4baddde45e4df154940c292df05448516fbf1c83))

##### Crud Controller

* Test su rotta 'detail' di CacheManager service ([5a2c0d](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/5a2c0dee5707384d54931bf4999afedb8fa7d4e2))

##### Dependencies

* Aggiunta dipendenza standard a laravel-api-model-driver per chiamate api su modelli ([3027a6](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/3027a6594271176680cf15f892d7d5fc7df5c741))

##### Docs

* Modifiche a view welcome + swagger ui docs ([0f494f](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/0f494f67bfce86b22fb637f8fc5e4368c8007c6f))

##### Elasticsearch

* Iniziato interfacciamento elasticsearch ([299b94](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/299b94a29e3176f4f3188322a95afcecad9a3b7d))

##### Enums

* Nuovi enums comuni a diverse requests ([7ca2a8](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/7ca2a85598837fcf4e116d87375dca91fcb9b190))

##### Grids

* AdminGridController->getGridsConfigs ([c5b02e](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/c5b02ecb34261aad0d1cde206b00d251b713c835))

##### Griglie

* Prove HasGridUtils in modelli ([67c9e9](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/67c9e903cd7a62479931a0608c52ac02a50dee6d))

##### Impersonificazione

* Aggiunto package laravel-impersonate ([985470](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/985470f856cfd258f8b4a09e8ceedfa1e39f0dff))

##### Merge

* Merge branch 'v2' of deneb:/var/lib/git/ease/laravel-boilerplate into v2 ([2b1e7e](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/2b1e7ef6442d8f29ddfdea9dba7e10859d39f95f))

##### Merge-plugin

* Creato merge-plugin custom con funzioni basilari di merge ([e1b2cf](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/e1b2cff0825459edb31da610ed1773b753bb57af))

##### Middlewares

* Api middlewares ([3385fc](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/3385fc0d5d71762298b66d234672010897c37802))
* Raggruppamento rtte per middlewares ([fe2b0f](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/fe2b0f60f35af7d8d6b0fe90cf30c54810defac5))

##### Migrations

* Added username on users table + added softDeletes on settings table ([10b161](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/10b16169b7a7d28f19744f63f5c517ba8234e23d))
* Aggiunti campi timestamps a pivot permissions e gruppi ([675c4f](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/675c4f7034390044132b433acd6d0a9b21152cb2))
* Modifiche a default migrations, rinominato Module Admin in Grids ([4de8a7](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/4de8a77b18d3341b7e418e241f32e8c16e0ff0ca))
* Preparazione database e aggiunta colonne comuni tabelle ([b3c17f](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/b3c17ffdfe3c0e86f7a1cf503860434cfeea156d))

##### Modelli

* Verificare funzionamento versionable su soft e force delete + pivot ([01a683](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/01a6830cdeb0ca2e4dfee7391bf3539ef7d81607))

##### Models

* Changes on Default Models configurations ([86ba1b](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/86ba1b9ed89c6e8b8c9790f313bc935b1f7f7ac5))
* Fixed wrong model class in acl configs + minors on models ([2c02c3](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/2c02c300f5d14b438e5d9fb57534ef6cae9f7797))
* Rules e models factories ([f3c417](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/f3c4178300cf52364ab908b67446f37708d0eb4b))
* Various models modifications ([c9eeed](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/c9eeed4b62f06a64533b204bf960cd86e1d56efd))

##### Modules

* Test creazione modulo contenuti con dipendenze e ovverride a vendor configs e migrations ([998dce](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/998dceb7a460ff3adb61437189a18b4d2445fa0d))

##### Moduli

* Modulo admin ([e6ddb6](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/e6ddb6ac5ab3641d32e23a25b8b8b34a025d1414))

##### Permissions

* Sostituita libreria gestione permissions modelli ([85fc7e](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/85fc7ee5dcb0a26c50ff1eb743e275f7f83509f1))

##### Recursive Relations

* Fix ciclo infinito in saved hook user + rotta tree ([d3ddeb](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/d3ddeb174724fb20e5b4d4ce60e07409f4e9eb1e))

##### Requests

* Creati oggetti da requests Crud ([b69f01](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/b69f01d9e0eceda8e2c9dfd3e43683f7d3726134))
* New middlewares and request parsers ([49967b](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/49967baae02c5f3f1f80ad19407f78210fcce0ab))
* New requests validations + new api routes ([e73b14](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/e73b14c8ad769002ef35aa067c9ec8c617da1547))
* Parse parametri request prima di validazioni ([ae67ab](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/ae67ab9ebb58e792ea2001e29f946ed89e32fd38))
* Parsers Requests ([ce9d4c](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/ce9d4c63ff782d93ec142cee0ba2a141d23de3de))

##### Requirements

* Aggiornati requirements in composer.json ([a6e37c](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/a6e37ccbfb3b1dd8bcf2d37fea3b5bb7d8515e4e))

##### Router

* Aggiunto nome a rotta documentazione swagger ([b38eff](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/b38effd56c4adebbc8622f91db9db76d218c7e52))

##### Routes

* Ristrette rotte docs, info, welcome a chiamate locali ([5b8c7c](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/5b8c7c999a1273104093c70ade7613526bade2bd))
* Rotta "ping" ([031e64](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/031e646028560e30f7968fbf203888c378bf19cb))
* Rotta SettingController@getGridConfigs per configurazioni griglie admin ([09162b](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/09162bb35607a607cd37122461649c49e6268adb))

##### Setup

* Aggiunt apossibilità di passare argomenti allo script di setup ([c25159](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/c2515970ebb7b713c6fc7aba0c0c07a73ebf620f), [c8d081](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/c8d081916a21f8e48a2dd6f9b19b8780fe6c01f9))

##### Swagger

* Aggiunto plugin per generazione da comando di documentazione rotte e parametri ([f56303](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/f5630359a907e6f78eaa833ab04b1af29b31c7ce))
* Documentazione swagger per moduli App,Crud,Admin ([bbabdd](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/bbabddb9d341b9541c8b6f2c7753686a4d63b162))

##### User

* Firme, documentazione, nuove rotte dati utente ([d46a44](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/d46a44e3612ecc40b9bf68dd781f2a44d9e93f3a))
* Rotte utente e requests validations ([012e46](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/012e4655ba3b84437b052077905d0cb4b9a6a72c))

##### Validazioni

* Validazioni requests moduelo Crud ([963557](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/963557a67f5db5032f3fa4209fdf7f6495b7d276))

### Bug Fixes


##### Casts

* Modificati nomi casts da datetime a date ([cc7dcc](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/cc7dcce57f407f6fdef2eccce25eb2743b90c34e))

##### Commands

* Corretto malfunzionamento elenco migrations per modulo App ([3fa9cb](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/3fa9cb90b1f4bb66fb675d23a3ef9ca23b6927d2))

##### Hooks

* Push changelog mancante ([9e5da5](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/9e5da5bbe12b9ffcb82627815e947d316d4acd55))

##### Migrations

* Acl table syntax fix ([5bb6b9](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/5bb6b9f526d984862adc3d56af19ed7500f1ac8b))

##### Setting Controller

* Fixed lang name in getTranslations ([106a7d](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/106a7dbffcf83bfa084e76d9abe9607b23ee6367))

##### Varie

* Fixes su DynamicEntity::resolve e migrations checks ([cb9154](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/cb9154531fbb61d1a0dcbacf1e41057a569f8fb5))

##### Vendors

* Forzate pubblicaizoni vendors e risolto problema migrations non volute ([e43045](https://bitbucket.org/willbit_dev/laravel-boilerplate/commit/e43045f27cb4d51b1dfb4cea0e1b913db1dc4b58))


---

