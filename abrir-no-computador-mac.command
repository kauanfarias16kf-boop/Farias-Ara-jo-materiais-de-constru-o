#!/bin/bash
cd "$(dirname "$0")"
if ! command -v php >/dev/null 2>&1; then
  echo ""
  echo "O PHP ainda não está instalado neste Mac."
  echo "Abra o Terminal e rode:  brew install php"
  echo "Depois dê dois cliques neste arquivo de novo."
  read -p "Aperte Enter para fechar."
  exit 1
fi
echo ""
echo " Sistema Farias Araújo rodando neste computador."
echo " Site:   http://127.0.0.1:8080"
echo " Painel: http://127.0.0.1:8080/painel"
echo ""
echo " Para desligar, feche esta janela."
(sleep 1; open "http://127.0.0.1:8080") &
php -d display_errors=0 -S 127.0.0.1:8080 -t site
