#!/bin/bash
# Test du parser avec base64

PDF_FILE="/Users/guillaumelecomte/Downloads/1503764583.pdf"
BASE64_DATA=$(base64 -i "$PDF_FILE")

curl -X POST https://gestnav.clubulmevasion.fr/parse_pdf_server.php \
  -H "Content-Type: application/json" \
  -d "{\"pdf_base64\": \"$BASE64_DATA\", \"filename\": \"1503764583.pdf\"}" \
  -v 2>&1 | tail -50
