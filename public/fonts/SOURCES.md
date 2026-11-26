# Sources des polices du document

Polices des offres et des factures (modèles du bâtiment, 2026-11-20). Deux jeux :

- `*.ttf` : fichiers statiques complets, pris dans le dépôt de l'éditeur de chaque famille, au commit cité. dompdf les
  lit pour le PDF et n'y garde que les caractères employés. Complets, pour les noms d'ici : š č ć đ ž ł ř ğ ş.
- `*-latin.woff2` et `*-latin-ext.woff2` : les mêmes faces par sous-ensemble (Fontsource), pour l'aperçu à l'écran
  (`public/css/document-ecran.css`). Le navigateur ne charge que les faces et les sous-ensembles employés.

`archivo-latin.woff2` et `inter-latin.woff2` sont les polices de l'application, hors de ce relevé.
Licences : `LICENCE-Roboto.txt` (Apache 2.0), `OFL-*.txt` (SIL Open Font License 1.1).
Relevé écrit par le script de téléchargement du 2026-11-20, complété le 2026-11-23 des italiques d'IBM Plex
(variantes du modèle Technique) ; `tests/Offre/PolicesDocumentTest.php` contrôle les empreintes.

| Fichier | Octets | SHA-256 | Source |
|---|---|---|---|
| `roboto-400.ttf` | 515100 | `56a45233d29f11b4dfb86d248e921939d115778f87325e7ae8cc108383d6664d` | https://raw.githubusercontent.com/googlefonts/roboto-2/38062f4b4a0be4346d07a928408da21602545e9e/src/hinted/Roboto-Regular.ttf |
| `roboto-500.ttf` | 511592 | `2879a5ecb7fbfa13a7fc3e2cdd7fecbf73aa45e91b541dfdfa2c442eed0aac21` | https://raw.githubusercontent.com/googlefonts/roboto-2/38062f4b4a0be4346d07a928408da21602545e9e/src/hinted/Roboto-Medium.ttf |
| `roboto-700.ttf` | 514260 | `61f89f8db49261c2f6106e8dccc35df7b2f7ed909020db40a3fc905e95f99334` | https://raw.githubusercontent.com/googlefonts/roboto-2/38062f4b4a0be4346d07a928408da21602545e9e/src/hinted/Roboto-Bold.ttf |
| `roboto-400-italic.ttf` | 533388 | `fa0b17bb4aaac4a1b2ee149dd4ca3b55e97d3077aa6ba9bb02541b316e7c46ce` | https://raw.githubusercontent.com/googlefonts/roboto-2/38062f4b4a0be4346d07a928408da21602545e9e/src/hinted/Roboto-Italic.ttf |
| `LICENCE-Roboto.txt` | 11357 | `c71d239df91726fc519c6eb72d318ec65820627232b2f796219e87dcf35d0ab4` | https://raw.githubusercontent.com/googlefonts/roboto-2/38062f4b4a0be4346d07a928408da21602545e9e/LICENSE |
| `archivo-600.ttf` | 186700 | `6598b6ed816158767a7633bdfb01d78daf54cab4cd4d9d3cc0cfd995f4723083` | https://raw.githubusercontent.com/Omnibus-Type/Archivo/211127690e8ff106c36c935f7e5e697114cff103/fonts/ttf/Archivo-SemiBold.ttf |
| `archivo-700.ttf` | 192180 | `951a0ebab63b1bb0d90a26c27625bda803d570dace3851fa2f1eea65852d8983` | https://raw.githubusercontent.com/Omnibus-Type/Archivo/211127690e8ff106c36c935f7e5e697114cff103/fonts/ttf/Archivo-Bold.ttf |
| `archivo-800.ttf` | 191700 | `9fff67922ae2bc376a9e5c6d644eebf8c5b17f62a0f38e5f016e4b7e3903662f` | https://raw.githubusercontent.com/Omnibus-Type/Archivo/211127690e8ff106c36c935f7e5e697114cff103/fonts/ttf/Archivo-ExtraBold.ttf |
| `barlow-condensed-500.ttf` | 103360 | `262bd143292ce479ee0cd09a42b47ab173fca8e9c6eb5ed0b5c8a845bc371d17` | https://raw.githubusercontent.com/google/fonts/869789e84da92782c144d83d69b64cec54905b5f/ofl/barlowcondensed/BarlowCondensed-Medium.ttf |
| `barlow-condensed-600.ttf` | 109428 | `7b619d14bc2327509a9ef32b0890f709626f7ecc9ff61191c2a4314c5499d2d9` | https://raw.githubusercontent.com/google/fonts/869789e84da92782c144d83d69b64cec54905b5f/ofl/barlowcondensed/BarlowCondensed-SemiBold.ttf |
| `barlow-condensed-700.ttf` | 109912 | `e476562ec9c1e16cf16475895b511f08c804f438cc9a9f80a44ea50a0eeb5b65` | https://raw.githubusercontent.com/google/fonts/869789e84da92782c144d83d69b64cec54905b5f/ofl/barlowcondensed/BarlowCondensed-Bold.ttf |
| `OFL-BarlowCondensed.txt` | 4377 | `186d750eb496a4c17a76385f82be6aea2ac1cf2de074a811d63786cf374ea73f` | https://raw.githubusercontent.com/google/fonts/869789e84da92782c144d83d69b64cec54905b5f/ofl/barlowcondensed/OFL.txt |
| `ibm-plex-sans-400.ttf` | 207768 | `b47ebd72b5ce53b60f909033ff84d6fc12358cc147e2981f8c321ad3f36246b6` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-sans/fonts/complete/ttf/IBMPlexSans-Regular.ttf |
| `ibm-plex-mono-400.ttf` | 173052 | `7c6fbddca4b700be918f5f6183d9bd4464fa427fe435f0b480d77fe2bb8c5a43` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-mono/fonts/complete/ttf/IBMPlexMono-Regular.ttf |
| `ibm-plex-sans-500.ttf` | 209592 | `a494d5a69fa094f7071a9ffbc98e86c85337d1a29f3fc1ff7c2e1c46161ddf27` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-sans/fonts/complete/ttf/IBMPlexSans-Medium.ttf |
| `ibm-plex-mono-500.ttf` | 174008 | `98fbd727aae340b236955879dabed4d991aac9e8e90b3b2a67ce4a59221cc97c` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-mono/fonts/complete/ttf/IBMPlexMono-Medium.ttf |
| `ibm-plex-sans-600.ttf` | 209768 | `c51119b25f4a296da2b66edf3201b9aa1735091ed7efc1fca0313346f3851a4d` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-sans/fonts/complete/ttf/IBMPlexSans-SemiBold.ttf |
| `ibm-plex-mono-600.ttf` | 174608 | `f04d7c488ddf7d1fa99f2574efc3406ea4cbe17bb1af3a1ab960f84d0c96a172` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-mono/fonts/complete/ttf/IBMPlexMono-SemiBold.ttf |
| `ibm-plex-sans-700.ttf` | 207396 | `22f59d358e8b9b9fb38ac6ee26a7ea7263ffa1c89a9056c101acff5add91f5d6` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-sans/fonts/complete/ttf/IBMPlexSans-Bold.ttf |
| `ibm-plex-mono-700.ttf` | 175096 | `74e5eedcfa4596497d34e19023cabdabd3a8c852b903007a5654a59591a72ffb` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-mono/fonts/complete/ttf/IBMPlexMono-Bold.ttf |
| `ibm-plex-sans-400-italic.ttf` | 215392 | `fbcfd6c96fb6188d3d65498e6f8e9719c2a3c0c2932e09255b01bd6d7c78ca12` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-sans/fonts/complete/ttf/IBMPlexSans-Italic.ttf |
| `ibm-plex-mono-400-italic.ttf` | 179756 | `a55368deb953f594ac18336a1029009ed820e2d9abbb57ad601f61e22bec072b` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/packages/plex-mono/fonts/complete/ttf/IBMPlexMono-Italic.ttf |
| `OFL-IBMPlex.txt` | 4456 | `7e6b2818edbd8f6a01ae80641cc8f16a51080d08fb4e532be3a0b6f74adb07da` | https://raw.githubusercontent.com/IBM/plex/2e90fe366fdc27c12682db13e5aa20ded3d90908/LICENSE.txt |
| `source-serif-4-400.ttf` | 261868 | `e5a4ee6a3d87bb9024796be390c6771e2a0eb1883dae25effaf57ca01668e24b` | https://raw.githubusercontent.com/adobe-fonts/source-serif/80d3f8894c09c937bebfa9011247d2e1c79fd6f4/TTF/SourceSerif4-Regular.ttf |
| `source-serif-4-600.ttf` | 272088 | `36db62940cb5728b12b1802476dc7fcf4c6c519a7bdd476ba23a4e555fc4655f` | https://raw.githubusercontent.com/adobe-fonts/source-serif/80d3f8894c09c937bebfa9011247d2e1c79fd6f4/TTF/SourceSerif4-Semibold.ttf |
| `source-serif-4-700.ttf` | 271568 | `7cf4f4e1ad74f45058d5bc61716b82560442fbdcd9d3654d2dea96bf6c683d86` | https://raw.githubusercontent.com/adobe-fonts/source-serif/80d3f8894c09c937bebfa9011247d2e1c79fd6f4/TTF/SourceSerif4-Bold.ttf |
| `source-serif-4-400-italic.ttf` | 187808 | `9d2950a8f1da66e21502c35d646a1d2148e79f9ea43fd2158cf02f5232e7f430` | https://raw.githubusercontent.com/adobe-fonts/source-serif/80d3f8894c09c937bebfa9011247d2e1c79fd6f4/TTF/SourceSerif4-It.ttf |
| `source-serif-4-600-italic.ttf` | 192392 | `d6a0a4317102a255a55850640332ba3acc7c872606a555516b4ecdb66f3c9899` | https://raw.githubusercontent.com/adobe-fonts/source-serif/80d3f8894c09c937bebfa9011247d2e1c79fd6f4/TTF/SourceSerif4-SemiboldIt.ttf |
| `OFL-SourceSerif4.txt` | 4491 | `c21d7293d87b6d7ab1d0229a2f55b77f33a7613a6a4e66f6693d68d7d8d09464` | https://raw.githubusercontent.com/adobe-fonts/source-serif/80d3f8894c09c937bebfa9011247d2e1c79fd6f4/LICENSE.md |
| `roboto-400-latin.woff2` | 21884 | `425c0713a8176f92273d378599c7eac57de7fafabd4bd0ed457b70eb8f80d371` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-400-normal.woff2 |
| `roboto-400-latin-ext.woff2` | 15040 | `5725eacca97303d8bce26f76cfcaee4393295bbf93c1eb6c3e5e4f260b2da189` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-ext-400-normal.woff2 |
| `roboto-500-latin.woff2` | 22200 | `5bcc3aa180e7f26f643cd5b2621cd7c2de193d0661d913a94afd3d4881a7a34b` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-500-normal.woff2 |
| `roboto-500-latin-ext.woff2` | 15160 | `1e597a6e3b163200475089698be3427ab1bb01553493d5b7d53694429948d8ec` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-ext-500-normal.woff2 |
| `roboto-700-latin.woff2` | 22240 | `b9d66d1708156f765ada51939bc24ed259dafa69eb631b36e443680fe9e15879` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-700-normal.woff2 |
| `roboto-700-latin-ext.woff2` | 15196 | `7673803a2d402018b1f726dded5bf2dbf2be4307039e8718f4bea654d6eca249` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-ext-700-normal.woff2 |
| `roboto-400-italic-latin.woff2` | 23672 | `5d0e978b6560631da2504e1612e91ee1ff959c04adae245eb320ac90cad2ba2f` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-400-italic.woff2 |
| `roboto-400-italic-latin-ext.woff2` | 16068 | `ad66d13b1a82df14c8937a7c547809471a3a42e67b0fc06c0aa7955f37b6ece9` | https://cdn.jsdelivr.net/fontsource/fonts/roboto@latest/latin-ext-400-italic.woff2 |
| `archivo-600-latin.woff2` | 13820 | `d9e8c29fdd348edde2a4f9aae438e569dd165bbbdebda78495824a2d9aaa67e8` | https://cdn.jsdelivr.net/fontsource/fonts/archivo@latest/latin-600-normal.woff2 |
| `archivo-600-latin-ext.woff2` | 12380 | `cd902f80bcf11cc6545648c156415deded5336611928c0fb6b02325ad3af13f0` | https://cdn.jsdelivr.net/fontsource/fonts/archivo@latest/latin-ext-600-normal.woff2 |
| `archivo-700-latin.woff2` | 14508 | `abada6cd4c92a9a706f6d7ed3189f322ff43dd78b9402c7d1137465b861d2a04` | https://cdn.jsdelivr.net/fontsource/fonts/archivo@latest/latin-700-normal.woff2 |
| `archivo-700-latin-ext.woff2` | 13272 | `8affe3b4758ec03c7c1a8b40c119209cbb1815fd836160570f94935ce9a683ec` | https://cdn.jsdelivr.net/fontsource/fonts/archivo@latest/latin-ext-700-normal.woff2 |
| `archivo-800-latin.woff2` | 14416 | `c26cda2400b374eb1adb6e0973c86f455e2df17ca57c6755eb37d7bde47663fb` | https://cdn.jsdelivr.net/fontsource/fonts/archivo@latest/latin-800-normal.woff2 |
| `archivo-800-latin-ext.woff2` | 13276 | `5aca026bdcb2d06623bcab485c8ed44ecaee07aa39c9b6fdbf855925a1e821b4` | https://cdn.jsdelivr.net/fontsource/fonts/archivo@latest/latin-ext-800-normal.woff2 |
| `barlow-condensed-500-latin.woff2` | 21424 | `460f141ec8f6c9a1516bfd2bd9fe71656246d7a9d04a0955faf53158d8970c4c` | https://cdn.jsdelivr.net/fontsource/fonts/barlow-condensed@latest/latin-500-normal.woff2 |
| `barlow-condensed-500-latin-ext.woff2` | 14180 | `a6ff9214bfa7832cb91d246454fd48b10ef921e8af052d479fdf1f0ecb903504` | https://cdn.jsdelivr.net/fontsource/fonts/barlow-condensed@latest/latin-ext-500-normal.woff2 |
| `barlow-condensed-600-latin.woff2` | 22308 | `215a93c696f442034a46fbb382958f753fda60e30490683aeea6b235fcbb2b66` | https://cdn.jsdelivr.net/fontsource/fonts/barlow-condensed@latest/latin-600-normal.woff2 |
| `barlow-condensed-600-latin-ext.woff2` | 14516 | `aef7edf54f79a5329fbca9c065b56dcec3852c680d58cf89a9ef2db6940ca4b3` | https://cdn.jsdelivr.net/fontsource/fonts/barlow-condensed@latest/latin-ext-600-normal.woff2 |
| `barlow-condensed-700-latin.woff2` | 22444 | `3787a5a419171630e6890cfa47c4da067474d005cd0ff8dc11ec090fdc3ee2b8` | https://cdn.jsdelivr.net/fontsource/fonts/barlow-condensed@latest/latin-700-normal.woff2 |
| `barlow-condensed-700-latin-ext.woff2` | 14640 | `9d351bd9222bfab90f943850f8039aa73cdb40e0a6cfe4127308a48bef59d5f5` | https://cdn.jsdelivr.net/fontsource/fonts/barlow-condensed@latest/latin-ext-700-normal.woff2 |
| `ibm-plex-sans-400-latin.woff2` | 22588 | `3b646991d30055a93a4ecc499713d4347953a74a947ecab435ab72070cbdab0e` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-400-normal.woff2 |
| `ibm-plex-sans-400-latin-ext.woff2` | 15980 | `c93d2a12aaa280f68b9ab7b726ff8dfedda67c99ef9abed047c1847a1cc6d583` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-ext-400-normal.woff2 |
| `ibm-plex-sans-500-latin.woff2` | 24184 | `0717336fb31fcdcde4b8deb3675bb4a0f7f6d484864afcd6751ac29975962203` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-500-normal.woff2 |
| `ibm-plex-sans-500-latin-ext.woff2` | 16456 | `2846035d85100f84c79393f80f1442d4ee720129ab8b3ffa8969aae281db8c6c` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-ext-500-normal.woff2 |
| `ibm-plex-sans-600-latin.woff2` | 24252 | `8960851d691c054ed38e259bdcf1a6190d157b4203ed5bb32c632a863fb8ec2f` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-600-normal.woff2 |
| `ibm-plex-sans-600-latin-ext.woff2` | 16448 | `b25dfd4f979e442ae1e25cd0894463434cf01ba21ac1a35d39f4a82bd4cc060e` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-ext-600-normal.woff2 |
| `ibm-plex-sans-700-latin.woff2` | 22832 | `42e7b0c143c19df9d99fd896e76b48f846edf0902d200bc29796b34d12c33aa7` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-700-normal.woff2 |
| `ibm-plex-sans-700-latin-ext.woff2` | 16188 | `92f0392bd74fa3955bfa92f6ec747e7246d3567b6f46b32ddbaf271104836ab8` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-ext-700-normal.woff2 |
| `ibm-plex-mono-400-latin.woff2` | 14708 | `08949f728dc52d528e69b1667d15c89a5686a4ee9a296ff90983985f99c380f7` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-400-normal.woff2 |
| `ibm-plex-mono-400-latin-ext.woff2` | 13348 | `6bc0f226a5b7884a8170e3f62c63d7675609d4631bdc5931b5cdab81821f00eb` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-ext-400-normal.woff2 |
| `ibm-plex-mono-500-latin.woff2` | 14888 | `01d285447409c8a588692162439a038b8cbd7871309ee20267b0d2d91c6e8e22` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-500-normal.woff2 |
| `ibm-plex-mono-500-latin-ext.woff2` | 13432 | `6bb06407c97584b0867a959e05e8874693bfeb8c317de190811c51598f2d99ea` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-ext-500-normal.woff2 |
| `ibm-plex-mono-600-latin.woff2` | 15620 | `0d1f0b8d0722224e32e9f28261bdc86c79115be73444ae5eceb73976a1bcdf83` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-600-normal.woff2 |
| `ibm-plex-mono-600-latin-ext.woff2` | 14328 | `32057cf50dd14bdb21a2c93766c4a2c43e4abe688ea3922df3203cac7751a98b` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-ext-600-normal.woff2 |
| `ibm-plex-mono-700-latin.woff2` | 14908 | `4f84d86cfd060f4ded334358ff8a4c81d4db2ed5addd568359d693f44a87765a` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-700-normal.woff2 |
| `ibm-plex-mono-700-latin-ext.woff2` | 13380 | `5b9b81f54dd69635c7adcaacd4c4545a73fe4809c528e22734b238a83a74135f` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-ext-700-normal.woff2 |
| `ibm-plex-sans-400-italic-latin.woff2` | 24356 | `6de912e531b6c98084f1b2d5e5a91bad77be4e68bc4e396e43c46fc435e5f3d9` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-400-italic.woff2 |
| `ibm-plex-sans-400-italic-latin-ext.woff2` | 17008 | `c5c00e8a32022dfedd2e7e864898ed007232e1450c6de1d6b847fe8825c2c5ce` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-sans@latest/latin-ext-400-italic.woff2 |
| `ibm-plex-mono-400-italic-latin.woff2` | 16448 | `0840095faae86403735a8c04014b72cb29e7923646b222b360b7e8252932e4e3` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-400-italic.woff2 |
| `ibm-plex-mono-400-italic-latin-ext.woff2` | 14524 | `1b07eee6df7d26b31864581c2365131d384077605e0b63ae67c4a0a64363a6de` | https://cdn.jsdelivr.net/fontsource/fonts/ibm-plex-mono@latest/latin-ext-400-italic.woff2 |
| `source-serif-4-400-latin.woff2` | 20088 | `02194deb92d3975dd30e11a3824a1f1db32b48c93654e60560cb81ce8e7b5f95` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-400-normal.woff2 |
| `source-serif-4-400-latin-ext.woff2` | 17844 | `78ff74ea29d0cc5594ff9efd5c0a30911691b89e9021e7bcf118b95e285e007f` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-ext-400-normal.woff2 |
| `source-serif-4-600-latin.woff2` | 21532 | `f2b7e1cf1d277b7608231868135648f8ad8e2b58d8e97ca088bee15dc357bee7` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-600-normal.woff2 |
| `source-serif-4-600-latin-ext.woff2` | 18332 | `ba70bfd7a5f52800fb9abedfe1926db2b1b614b10cb8ac1e5798a14fb06a02d4` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-ext-600-normal.woff2 |
| `source-serif-4-700-latin.woff2` | 21716 | `7691c51bc286a9014db0048277d2c3f2ad0a90b533dc8adfb16cb22a95390d39` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-700-normal.woff2 |
| `source-serif-4-700-latin-ext.woff2` | 18468 | `06e2c39d92b45e61e0988b141fdc61632f77e5e7d793e225ee7eb9e307333308` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-ext-700-normal.woff2 |
| `source-serif-4-400-italic-latin.woff2` | 20092 | `882b7c150c29f29d4f8daae6b8dcae8662aac7f28ca34b34615305154136ecfd` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-400-italic.woff2 |
| `source-serif-4-400-italic-latin-ext.woff2` | 18452 | `d06253118f11f443e5799217c5ecaf55f68a1681115fa3a1d947e8ca288e7b8c` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-ext-400-italic.woff2 |
| `source-serif-4-600-italic-latin.woff2` | 21588 | `cdee523b89692709393643e25fdc066be137d5b0b9782548779e8382c3c11ce7` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-600-italic.woff2 |
| `source-serif-4-600-italic-latin-ext.woff2` | 19056 | `8518444dbc13488faf0ac5787f5608d0a9649fcb5684b84ef482c251c8e57cb7` | https://cdn.jsdelivr.net/fontsource/fonts/source-serif-4@latest/latin-ext-600-italic.woff2 |
