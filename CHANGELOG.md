## [1.3.3](https://github.com/NoiXdev/inventorix/compare/v1.3.2...v) (2026-07-01)


### Bug Fixes

* **settings:** apply runtime settings on Livewire requests ([cd43611](https://github.com/NoiXdev/inventorix/commit/cd436117cd7f6374a7414c750ed4c478a29b40f6))

## [1.3.2](https://github.com/NoiXdev/inventorix/compare/v1.3.1...v1.3.2) (2026-07-01)


### Features

* **assets:** add serial number filter and global search ([6cf654f](https://github.com/NoiXdev/inventorix/commit/6cf654f6be157f0615390f6be96cad8b3466e9c7))

## [1.3.1](https://github.com/NoiXdev/inventorix/compare/v1.3.0...v1.3.1) (2026-06-30)


### Features

* **storage:** add league/flysystem-aws-s3-v3 for the S3 disk driver ([bf276af](https://github.com/NoiXdev/inventorix/commit/bf276af737616f2b081602508a452b91d174b2e4))

## [1.3.0](https://github.com/NoiXdev/inventorix/compare/v1.2.1...v1.3.0) (2026-06-30)


### Features

* adjust section size ([bd1009b](https://github.com/NoiXdev/inventorix/commit/bd1009bbcc017eef84071f86446632e952486d5f))
* **assets:** add asset importer with id/enum/date handling ([1552eb7](https://github.com/NoiXdev/inventorix/commit/1552eb782855fe0dfb1fe80fc73dd43cb102682d))
* **assets:** add CSV/XLSX export action ([9d90b59](https://github.com/NoiXdev/inventorix/commit/9d90b5965a2a260e360fe6f00329108bc5b818e3))
* **assets:** export dates as German d.m.Y without time component ([a6fcbfd](https://github.com/NoiXdev/inventorix/commit/a6fcbfdeedb5cbf9be471b22b3e732558d464c70))
* **assets:** resolve and auto-create asset owners on import ([3cfba23](https://github.com/NoiXdev/inventorix/commit/3cfba23f7bd82ee6f6ece86d02af3d0ca761c768))
* **assets:** resolve manufacturer, model and place on import ([f2c34c7](https://github.com/NoiXdev/inventorix/commit/f2c34c713cbd394693bd407ce2d8a2a1d7f7ddff))
* **assets:** sync tags on import and register import action ([2ee5d35](https://github.com/NoiXdev/inventorix/commit/2ee5d35365f1c8fd5edab981bd156adac96c0004))
* **attachments:** add attachments table, model, enum and factory ([0d565d1](https://github.com/NoiXdev/inventorix/commit/0d565d15b3826a157ea3e251832102a9fec5788e))
* **attachments:** add HasAttachments trait and wire to Asset ([70d7722](https://github.com/NoiXdev/inventorix/commit/70d7722f3c5e8ac66bebd85e883d6eb4e1eec1e0))
* **attachments:** Anhänge relation manager with upload, download, delete ([b4df577](https://github.com/NoiXdev/inventorix/commit/b4df577c4508952ffc0e53fc1f4b3f01c676f94c))
* **attachments:** observer for file cleanup and activity log ([117c16c](https://github.com/NoiXdev/inventorix/commit/117c16ceadc5eb6a0a95856ebdaac482ccec0634))
* **panel:** enable database notifications for import/export completion ([851040f](https://github.com/NoiXdev/inventorix/commit/851040f1e9a750db74cf8bd7e80b4849035e3eeb))
* **storage:** apply S3 settings to runtime config with local fallback ([f73c428](https://github.com/NoiXdev/inventorix/commit/f73c428e138105f4929d826788213fca158f5724))
* **storage:** Storage settings page with S3 config and connection test ([c6dea03](https://github.com/NoiXdev/inventorix/commit/c6dea03f4989f2ac4f4c95a5166c645b743b40c2))
* **storage:** StorageSettings class and migration ([f841843](https://github.com/NoiXdev/inventorix/commit/f84184342dfe8dccf2d238aed04909ed677f1554))
* **warranty:** add daily scan-expiries command + schedule ([e11d8e5](https://github.com/NoiXdev/inventorix/commit/e11d8e5524918208930d54a7c052b93bab0855bc))
* **warranty:** add grouped warranty expiry digest mailable + view ([3c32740](https://github.com/NoiXdev/inventorix/commit/3c3274033532d5977e69ec173827b435f8ff20e4))
* **warranty:** add soonest-expiring assets table widget ([1af5547](https://github.com/NoiXdev/inventorix/commit/1af5547668bffc0ac2ccbad7d6c21b252359e30d))
* **warranty:** add warranty notification settings page ([54a5be4](https://github.com/NoiXdev/inventorix/commit/54a5be4809258f14aa7b712fd3fa83b7098694b8))
* **warranty:** add warranty stats dashboard widget ([44134f6](https://github.com/NoiXdev/inventorix/commit/44134f6e4e0704a6130ba8eb378eca353cfb73f8))
* **warranty:** add warranty_notifications ledger model + table ([b6c3d48](https://github.com/NoiXdev/inventorix/commit/b6c3d486e041fa08449fc9248557753c2a175ff1))
* **warranty:** add WarrantyScanner milestone detection with ledger dedupe ([ea0ecac](https://github.com/NoiXdev/inventorix/commit/ea0ecacb3917d3d9c37c9c465ca79ed843528630))
* **warranty:** add WarrantySettings with seeded defaults ([fc5f8ab](https://github.com/NoiXdev/inventorix/commit/fc5f8ab88705d3ca138145633bfb93b1697f3564))


### Bug Fixes

* **assets:** german plurals, eager-load export relations, labels ([a3cc5fd](https://github.com/NoiXdev/inventorix/commit/a3cc5fd8a8a1e5bc6226e4977f92a2cfe8ee0222))
* **assets:** require asset_type on import, revert nullable hack ([66515a3](https://github.com/NoiXdev/inventorix/commit/66515a37e85b15b8bffa4f2dc6a823b7c049aff3))
* **attachments:** cascade-delete files on asset delete; per-file titles; safe size format ([c2d94ea](https://github.com/NoiXdev/inventorix/commit/c2d94ea8099e9b3b347b707ace4d297ccc75d9bf))
* **panel:** use uuidMorphs for notifications notifiable_id ([1460ade](https://github.com/NoiXdev/inventorix/commit/1460ade81b4994fd143e71029480bca4f92310d8))
* pint clenup ([0e52fa9](https://github.com/NoiXdev/inventorix/commit/0e52fa917c3288008295a5335d0569a0d2e27ce7))
* **storage:** connection test detects real failures (s3 disk throw=false) ([bcd8c51](https://github.com/NoiXdev/inventorix/commit/bcd8c514c325b1e2b99dbf22a2702934fa835f5b))

## [1.2.1](https://github.com/NoiXdev/inventorix/compare/v1.2.0...v1.2.1) (2026-06-12)


### Bug Fixes

* **auth:** hide Entra login button on MFA challenge step ([df36bd5](https://github.com/NoiXdev/inventorix/commit/df36bd559280ef5fa27e41d38e9eb7b6303007e2))

## [1.2.0](https://github.com/NoiXdev/inventorix/compare/v1.1.1...v1.2.0) (2026-06-10)


### Features

* adding multi factor and profile page in filament ([f04227e](https://github.com/NoiXdev/inventorix/commit/f04227ebc7f0880ea244aa70c5c4786b020c6f06))
* **auth:** add multi-factor settings to auth settings page ([849f1e3](https://github.com/NoiXdev/inventorix/commit/849f1e39288f96b1e82bbb7c789815193485c26b))
* **scanner:** add camera-error classifier with tests ([684fad9](https://github.com/NoiXdev/inventorix/commit/684fad99f109334f25d54071a38c1cfbdbb46065))
* **scanner:** add German camera-error messages ([2c91dd5](https://github.com/NoiXdev/inventorix/commit/2c91dd5b38bd395c636f8e005c30b66bfa502bc4))
* **scanner:** add inline error element and message data attributes ([ba6adf3](https://github.com/NoiXdev/inventorix/commit/ba6adf3134938837694f0e9215e36e5408973075))
* **scanner:** show inline message on camera permission/availability errors ([756e3ad](https://github.com/NoiXdev/inventorix/commit/756e3ad4e688c0e836a3aea54549ed5f5333d350))
* setting correct auth config for multi factor auth ([1e9a0bf](https://github.com/NoiXdev/inventorix/commit/1e9a0bf4dc42c28450268529dae3b348b863c17e))

## [1.1.1](https://github.com/NoiXdev/inventorix/compare/v1.1.0...v1.1.1) (2026-06-10)


### Features

* **auth:** add Authentication settings page ([8ce7ad5](https://github.com/NoiXdev/inventorix/commit/8ce7ad52e1936988348754f6ccebadeafaf7969b))
* **auth:** add Authentication settings translations ([4cb9ecd](https://github.com/NoiXdev/inventorix/commit/4cb9ecdbb7dc2e0d9c127ca9affe76e2f19fd1ed))
* **auth:** add AuthSettings with Microsoft Azure fields ([3f2dc38](https://github.com/NoiXdev/inventorix/commit/3f2dc386e45caed9676d221c52a67b5624aa9f9c))
* **auth:** apply Microsoft Azure settings to runtime config ([bbefc2d](https://github.com/NoiXdev/inventorix/commit/bbefc2d279e30e740b33649c98e841461c2cfff5))
* **auth:** drive Microsoft Azure routes from DB settings ([3b2a0cb](https://github.com/NoiXdev/inventorix/commit/3b2a0cbd8acc1d9239bc230a4df48fe2afa177ab))
* **auth:** require Microsoft credentials when SSO is enabled ([faaf6f2](https://github.com/NoiXdev/inventorix/commit/faaf6f244fc84df35b49cf6b40e9c6ac5d2b5d4c))


### Bug Fixes

* navigation sort for settings cluster ([b3b9836](https://github.com/NoiXdev/inventorix/commit/b3b983687f84ef7cbd3636e16a3f823df3fa84f6))

## [1.1.0](https://github.com/NoiXdev/inventorix/compare/v1.0.2...v1.1.0) (2026-06-10)


### Features

* add General settings page to Settings cluster ([43efe1c](https://github.com/NoiXdev/inventorix/commit/43efe1c548e45efc1f7f0de402ad154210a1d73c))
* add GeneralSettings with app_name ([fddb119](https://github.com/NoiXdev/inventorix/commit/fddb1194ec00ed6832cb4def3cb22bd2db58c746))
* add Mail settings page with driver picker and masked secrets ([6eb9a2c](https://github.com/NoiXdev/inventorix/commit/6eb9a2c8acbe39550576f68b8a42c93c25aaee02))
* add MailSettings settings class ([1da87b9](https://github.com/NoiXdev/inventorix/commit/1da87b92f413f5d4cadce437023344cd46e65dd6))
* add postal mailer ([13d8a7e](https://github.com/NoiXdev/inventorix/commit/13d8a7eb5434ef98e593d3a9cedf0654c0bc2fdd))
* add send-test-email action to Mail settings page ([b25f81d](https://github.com/NoiXdev/inventorix/commit/b25f81d241c5e04198742903d26dd8a076c7e343))
* add Settings cluster to app panel ([41019a4](https://github.com/NoiXdev/inventorix/commit/41019a4316bb5be7f001c07b7382368595df28a0))
* apply DB mail/general settings to runtime config ([e841569](https://github.com/NoiXdev/inventorix/commit/e841569b0ce5124f6ff1790d70bf12ddfef192bb))
* apply runtime mail settings before each queued job ([c29a8b2](https://github.com/NoiXdev/inventorix/commit/c29a8b261c018d801ef9d9f43f37ce94422bd958))
* apply runtime mail settings on each panel request ([ef22078](https://github.com/NoiXdev/inventorix/commit/ef2207800e2b2e53105bdf0f788d9855b8d3e537))
* **evaluation:** add aging/replacement report ([72f9d79](https://github.com/NoiXdev/inventorix/commit/72f9d793ff3575b56cdca24c4a9fc65e7da1a283))
* **evaluation:** add asset value report with aggregated and detailed modes ([cebd681](https://github.com/NoiXdev/inventorix/commit/cebd681bd8f8cdfb3f6189dfa1bdaacbc61c81e6))
* **evaluation:** add assets-per-employee report with PDF and export ([3407323](https://github.com/NoiXdev/inventorix/commit/3407323af3e66c643d9b05663711b1f54842b2e2))
* **evaluation:** add BaseReportPage abstract with table, PDF and export wiring ([d11e25e](https://github.com/NoiXdev/inventorix/commit/d11e25e094f5447d1af1b18a16960684c0045fb1))
* **evaluation:** add guarantee status report ([de7184e](https://github.com/NoiXdev/inventorix/commit/de7184e582be5162e93bb6a94d5dfc23e5762c52))
* **evaluation:** add handover history report ([5784a49](https://github.com/NoiXdev/inventorix/commit/5784a49a88689263df4fc44bd21324297fbd2460))
* **evaluation:** add inventory by location report ([a05d9e4](https://github.com/NoiXdev/inventorix/commit/a05d9e4996a950ee3b7c8a1c3f78dcf7dbbc4979))
* **evaluation:** add repair/incident history report ([362a170](https://github.com/NoiXdev/inventorix/commit/362a1703386660df551b8e3403dc2860e8d73255))
* **evaluation:** add ReportColumn value object ([5f63804](https://github.com/NoiXdev/inventorix/commit/5f638047aff14f41285bfcf68cb96ecb2d8325e1))
* **evaluation:** add ReportExportService for CSV/XLSX downloads ([d5e2f62](https://github.com/NoiXdev/inventorix/commit/d5e2f62314627ca05beac2874abe127212da14be))
* **evaluation:** add shared report page and PDF layout views ([ee9c43a](https://github.com/NoiXdev/inventorix/commit/ee9c43af3d3f737aca018e801b26736fc1abf384))
* **evaluation:** add state overview report ([124fc54](https://github.com/NoiXdev/inventorix/commit/124fc542bf5f1a26217723345f82d0b80b568cc8))
* **evaluation:** add table pagination toggle and column summaries to BaseReportPage ([b80ec36](https://github.com/NoiXdev/inventorix/commit/b80ec3612f9d5a3843ae5f04ed9eb549e200aa8b))
* **evaluation:** group assets-per-employee PDF one page per employee ([b372c9e](https://github.com/NoiXdev/inventorix/commit/b372c9ec4bd698693292646ca171f56e7d6469ba))
* **evaluation:** render report cards on Evaluation index ([7702d22](https://github.com/NoiXdev/inventorix/commit/7702d220071536e9fc927ea24fdae5ba591665e2))
* seed mail settings from env ([6e2a734](https://github.com/NoiXdev/inventorix/commit/6e2a734866524f8f0a0d4a8833ff94cbe6890a9e))
* translate Settings cluster and pages (de) ([e171a56](https://github.com/NoiXdev/inventorix/commit/e171a56d60f6b8ff90b85d647e04235c64f59465))
* **translations:** add missing user translation ([24cefbd](https://github.com/NoiXdev/inventorix/commit/24cefbd348d395a2969cab59e9e57e06437d3553))


### Bug Fixes

* **evaluation:** handle null group key in aggregation report tables ([42d6a4c](https://github.com/NoiXdev/inventorix/commit/42d6a4c72ce389c939c907e18fe9c312cca7cbbe))
* render TestMail as markdown so mail components resolve ([3a22fcb](https://github.com/NoiXdev/inventorix/commit/3a22fcbafc2533af91cae7b20d3c1d24450bdd4c))
* **scanner:** fix scanner and adding tests ([f6210e9](https://github.com/NoiXdev/inventorix/commit/f6210e9bf620ca8cfe6a53fd73354c048a141c49))
* suppress duplicate save toast and cover test-email failure path ([24f2d8f](https://github.com/NoiXdev/inventorix/commit/24f2d8f2edce13276ca9484e6d4a89ed232439bd))
* use valid SMTP scheme values (smtp/smtps) instead of tls/ssl ([526cdd0](https://github.com/NoiXdev/inventorix/commit/526cdd033cb82311b0f155a9f21f0ba803dc1692))

## [1.0.2](https://github.com/NoiXdev/inventorix/compare/v1.0.1...v1.0.2) (2026-06-09)


### Bug Fixes

* force ssl and fix proxies ([120ce7f](https://github.com/NoiXdev/inventorix/commit/120ce7fc95fad67acb8a0f150c2876020a8dc2e1))

## [1.0.1](https://github.com/NoiXdev/inventorix/compare/v1.0.0...v1.0.1) (2026-06-09)


### Features

* adding redirect from / to /app ([c7c10d3](https://github.com/NoiXdev/inventorix/commit/c7c10d34fce0e2f92ea3197856529d0606aeff01))
* adding redirect from / to /app ([367df4a](https://github.com/NoiXdev/inventorix/commit/367df4a78b04dda8b9243b5d40b9edaede0bb7fb))


### Bug Fixes

* addding filament/livewire assets publish ([e8524ed](https://github.com/NoiXdev/inventorix/commit/e8524ed69482cbe164a343846a98380e1c0c2f8a))
* **release:** make version single-sourced so it never drifts ([f69f2e6](https://github.com/NoiXdev/inventorix/commit/f69f2e64a663cba9dbf06696a252540673ccbee6))

## [1.0.0](https://github.com/NoiXdev/inventorix/compare/053225ce9019a3a177466eb4d5218ea14496e8ed...v1.0.0) (2026-05-26)


### Features

* initial public release ([053225c](https://github.com/NoiXdev/inventorix/commit/053225ce9019a3a177466eb4d5218ea14496e8ed))

