#!/bin/bash
# Envoie calib.sh au site de test et garde la sortie dans le scratchpad.
D=/mnt/c/Users/xavie/AppData/Local/Temp/claude/42fd285555aa8bf3/97c26926-6798-47a0-9ab4-07d5bd6c9c8b/scratchpad
ssh -i ~/.ssh/offregen_test -o BatchMode=yes 3p81e5_easytest@3p81e5.ftp.infomaniak.com 'bash -s' -- \
  7:2/8201ab54-af8d-45ca-a0c1-12f3593b8f27.ogg \
  8:1/c3baf703-4c4d-471c-889b-e1f6780a9692.webm \
  9:1/1a83f47a-cc9b-49e0-8624-a36d698e9be1.webm \
  10:1/4169076b-5e61-4468-a459-f8a620164efa.webm \
  11:17/7016e9d3-07b0-4de2-8044-75a6d9057f43.webm \
  12:17/ac82837e-8d4d-4cc1-8ef6-097f7d9fa965.webm \
  13:19/fa16c9e2-b1d0-47e2-b7e7-997e6f474374.webm \
  14:20/38be7aeb-af52-48b5-9af0-57706677083d.webm \
  15:19/f36fff83-5343-4afd-9a5d-38f7fa679f01.webm \
  16:22/69a4c260-8caa-481b-9df1-320866bf6ec8.webm \
  17:5/fec9ce41-81be-41e3-afa4-cfc850b83e37.webm \
  18:16/f5de67e4-468f-4894-898d-a4c48e95db49.webm \
  19:16/dd5a2160-ff78-46c5-9aae-796d61ce95aa.webm \
  20:5/9b841c00-35b2-42d1-9328-4258bb5b5935.webm \
  21:5/deb32709-b9ce-48be-a248-e992546c9233.webm \
  22:22/57a81a56-dff1-41cd-9576-18d7926fdb9d.m4a \
  23:5/c6ff740c-69e0-456a-88c1-d5cf088a05a5.webm \
  24:5/270b3203-d87b-483c-9528-8112e10fd0c9.webm \
  25:16/277e58a0-d62e-4968-a575-0c38d9093fdb.webm \
  26:16/0c2e4395-d977-4bfe-9bf0-3b3352b558ce.webm \
  27:5/f6a1c400-38a0-401c-9d8b-a32671a6d567.webm \
  28:5/50749d09-52d5-4f49-a0e8-1086e2a2ec37.webm \
  < "$D/scripts/calib.sh" > "$D/calib.txt" 2>&1
echo "fini : $(grep -c '^=====' "$D/calib.txt") enregistrements"
