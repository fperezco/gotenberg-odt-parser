#!/bin/bash
curl -X POST http://localhost/process-document \
  -F "template=@example_odt_template.odt" \
  -F 'parameters={"client_name":"Jose Sanchez","payment_amount":"3500 €","email_contact":"contact@4a-side.ninja"}' \
  --output output.pdf
