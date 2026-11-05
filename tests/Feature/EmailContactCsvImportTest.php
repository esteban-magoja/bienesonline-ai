<?php

declare(strict_types=1);

use App\Filament\Resources\EmailContacts\Pages\ListEmailContacts;
use App\Models\EmailContact;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\AdminEmail\AdminEmailService;
use App\Services\AdminEmail\EmailContactCsvImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Queue::fake();
    config(['admin_email.mailer' => 'array']);
});

function csvFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'contacts') . '.csv';
    file_put_contents($path, $content);

    return $path;
}

function importCsv(string $content, ?string $source = null): array
{
    $path = csvFile($content);

    try {
        return app(EmailContactCsvImporter::class)->import($path, $source);
    } finally {
        @unlink($path);
    }
}

it('importa contactos reconociendo encabezados en español con acentos y separador punto y coma', function (): void {
    $result = importCsv("Nombre;Inmobiliaria;Correo electrónico;País;Teléfono;Notas\nAna Pérez;Ana Propiedades;ANA.CSV@example.com;España;+34 600 111 222;Conocida en feria\n", 'Feria test');

    expect($result)->toMatchArray(['created' => 1, 'invalid' => 0]);

    expect(EmailContact::where('email', 'ana.csv@example.com')->sole())
        ->name->toBe('Ana Pérez')
        ->agency->toBe('Ana Propiedades')
        ->country->toBe('Spain')
        ->whatsapp->toBe('+34 600 111 222')
        ->notes->toBe('Conocida en feria')
        ->source->toBe('Feria test');
});

it('acepta encabezados en inglés, coma como separador y códigos de país', function (): void {
    $result = importCsv("email,name,company,country,phone\nbob.csv@example.com,Bob,Bob Realty,AR,123\nmx.csv@example.com,,Casa MX,méxico,\n");

    expect($result['created'])->toBe(2)
        ->and(EmailContact::where('email', 'bob.csv@example.com')->value('country'))->toBe('Argentina')
        ->and(EmailContact::where('email', 'mx.csv@example.com')->value('country'))->toBe('Mexico');
});

it('convierte archivos de Excel en Windows-1252 y quita el BOM de UTF-8', function (): void {
    importCsv(mb_convert_encoding("nombre;email\nJosé Núñez;jose.csv@example.com\n", 'Windows-1252', 'UTF-8'));
    importCsv("\xEF\xBB\xBFemail;nombre\nbom.csv@example.com;Con BOM\n");

    expect(EmailContact::where('email', 'jose.csv@example.com')->value('name'))->toBe('José Núñez')
        ->and(EmailContact::where('email', 'bom.csv@example.com')->value('name'))->toBe('Con BOM');
});

it('acepta un archivo sin encabezado con un email por línea', function (): void {
    $result = importCsv("solo1.csv@example.com\nsolo2.csv@example.com\n\nno-es-email\n");

    expect($result)->toMatchArray(['created' => 2, 'invalid' => 1]);
});

it('no duplica: completa solo los campos vacíos de contactos existentes', function (): void {
    EmailContact::factory()->create(['email' => 'existe.csv@example.com', 'name' => 'Nombre cargado a mano', 'agency' => null, 'whatsapp' => null]);

    $result = importCsv("email;nombre;inmobiliaria\nexiste.csv@example.com;Otro nombre;Nueva Inmo\nexiste.csv@example.com;Repetido;\n");

    expect($result)->toMatchArray(['created' => 0, 'updated' => 1, 'duplicates' => 1])
        ->and(EmailContact::where('email', 'existe.csv@example.com')->sole())
        ->name->toBe('Nombre cargado a mano')
        ->agency->toBe('Nueva Inmo');

    expect(importCsv("email;nombre\nexiste.csv@example.com;Otro\n"))->toMatchArray(['unchanged' => 1]);
});

it('no crea contactos para emails de usuarios registrados pero los devuelve para el envío', function (): void {
    $user = User::factory()->create(['avatar' => 'demo/default.png', 'email' => 'Usuario.CSV@example.com']);

    $result = importCsv("email\nusuario.csv@example.com\n");

    expect($result)->toMatchArray(['users' => 1, 'created' => 0, 'user_ids' => [$user->id]])
        ->and(EmailContact::where('email', 'usuario.csv@example.com')->exists())->toBeFalse();
});

it('informa las líneas con emails inválidos', function (): void {
    $result = importCsv("email;nombre\nbien.csv@example.com;A\nmal-email;B\n;C\n");

    expect($result)->toMatchArray(['created' => 1, 'invalid' => 2, 'invalid_lines' => [3, 4]]);
});

it('rechaza archivos sin columna de email', function (): void {
    importCsv("nombre;telefono\nAna;123\n");
})->throws(InvalidArgumentException::class, 'columna de email');

describe('panel admin', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        $admin = User::factory()->create(['avatar' => 'demo/default.png']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->actingAs($admin);
    });

    it('importa desde el botón y envía el mensaje a todos los del archivo', function (): void {
        $template = EmailTemplate::factory()->create();
        $user = User::factory()->create(['avatar' => 'demo/default.png', 'email' => 'registrado.csv@example.com']);
        $alreadyReceived = EmailContact::factory()->create(['email' => 'ya.recibio.csv@example.com']);
        app(AdminEmailService::class)->send($template, [], [$alreadyReceived->id]);

        $file = UploadedFile::fake()->createWithContent('contactos.csv', "email;nombre\nnuevo.csv@example.com;Nuevo\nregistrado.csv@example.com;\nya.recibio.csv@example.com;\n");

        Livewire::test(ListEmailContacts::class)
            ->callAction('importCsv', data: ['file' => $file, 'source' => 'Prueba', 'email_template_id' => $template->id, 'exclude_already_sent' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Importación terminada');

        expect(EmailContact::where('email', 'nuevo.csv@example.com')->value('source'))->toBe('Prueba');

        $lastSend = $template->sends()->latest('id')->first();
        expect($lastSend->recipients()->pluck('email')->sort()->values()->all())
            ->toBe(['nuevo.csv@example.com', 'registrado.csv@example.com'])
            ->and($lastSend->recipients()->where('email', 'registrado.csv@example.com')->value('user_id'))->toBe($user->id);

        expect(Storage::disk('local')->allFiles('tmp/email-contact-imports'))->toBeEmpty();
    });

    it('importa sin enviar si no se elige mensaje', function (): void {
        $file = UploadedFile::fake()->createWithContent('contactos.csv', "email\nsolo.importar.csv@example.com\n");

        Livewire::test(ListEmailContacts::class)
            ->callAction('importCsv', data: ['file' => $file])
            ->assertHasNoActionErrors();

        expect(EmailContact::where('email', 'solo.importar.csv@example.com')->exists())->toBeTrue();
    });
});
