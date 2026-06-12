## [1.2.1](https://github.com/NoiXdev/inventorix/compare/v1.2.0...v) (2026-06-12)


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

