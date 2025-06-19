# Gotenberg Gateway Service

A microservice that processes ODT templates and converts them to PDF using Gotenberg. This service allows you to:
1. Replace template variables in ODT files
2. Convert the processed ODT files to PDF
3. Handle the entire process through a simple REST API

## Features

- Template variable substitution in ODT files (using `{{variable}}` syntax)
- PDF conversion using Gotenberg
- Clean REST API interface
- Docker-based deployment
- Automated cleanup of temporary files

## Requirements

- Docker
- Docker Compose
- Git

## Installation

1. Clone the repository:
```bash
git clone https://github.com/fperezco/gotenberg-odt-parser.git
cd gotenberg-odt-parser
```

2. Create a `.env.local` file and configure your environment:
```bash
cp .env .env.local
```

3. Build and start the containers:
```bash
docker compose up -d
```


## Configuration

### Environment Variables

| Variable       | Description                  | Default                                              |
|---------------|------------------------------|------------------------------------------------------|
| GOTENBERG_URL | URL of the Gotenberg service | https://demo.gotenberg.dev/forms/libreoffice/convert |



## Usage

### REST API Endpoint

The service exposes a single endpoint:

- **URL**: `/process-document`
- **Method**: `POST`
- **Content-Type**: `multipart/form-data`

#### Request Parameters

| Parameter  | Type   | Description |
|------------|--------|-------------|
| template   | File   | The ODT template file containing variables to replace |
| parameters | JSON   | JSON object with key-value pairs for variable substitution |

#### Example using curl

```bash
curl -X POST http://your-domain/process-document \
  -F "template=@path/to/your/template.odt" \
  -F 'parameters={"client_name":"John Doe","payment_amount":"1000 €","email":"john@example.com"}'
```

#### Example Response

- Success: Returns the PDF file with `Content-Type: application/pdf`
- Error: Returns a JSON object with error details
```json
{
    "error": "Error message description"
}
```

### Integration with Symfony Applications

To integrate with another Symfony application, you can use the HttpClient component:

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YourService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function processDocument(string $templatePath, array $parameters): string
    {
        $response = $this->httpClient->request('POST', 'http://gutenberg-gateway/process-document', [
            'multipart' => [
                [
                    'name' => 'template',
                    'filename' => basename($templatePath),
                    'contents' => fopen($templatePath, 'r')
                ],
                [
                    'name' => 'parameters',
                    'contents' => json_encode($parameters)
                ]
            ]
        ]);

        return $response->getContent();
    }
}
```

### Template Format

The ODT templates should use the following syntax for variables:
- Use double curly braces: `{{variable_name}}`
- Example: `Dear {{client_name}}, your payment of {{payment_amount}} has been received.`

## Development

### Running Tests

```bash
docker compose exec app php bin/phpunit
```

### Project Structure

- `src/Controller/DocumentProcessController.php`: Main API endpoint
- `src/Service/OdtTemplateProcessor.php`: Handles ODT template processing
- `src/Service/GotenbergConverter.php`: Handles PDF conversion
- `tests/`: Test suite

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details. 