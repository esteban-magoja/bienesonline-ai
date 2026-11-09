<?php

declare(strict_types=1);

use App\Filament\Resources\EmailContacts\Pages\CreateEmailContact;
use App\Filament\Resources\EmailContacts\Pages\EditEmailContact;
use App\Filament\Resources\EmailContacts\Pages\ListEmailContacts;
use App\Filament\Resources\EmailContacts\RelationManagers\EmailRecipientsRelationManager;
use App\Filament\Resources\EmailSends\Pages\ViewEmailSend;
use App\Filament\Resources\EmailSuppressions\Pages\ListEmailSuppressions;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Resources\UserManagement\Pages\ListUserManagement;
use App\Jobs\SendAdminEmailBatchJob;
use App\Listeners\LinkEmailContactToUser;
use App\Mail\AdminMessageMail;
use App\Models\EmailContact;
use App\Models\EmailSend;
use App\Models\EmailSendRecipient;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\AdminEmail\AdminEmailService;
use App\Services\AdminEmail\SuppressionListParser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config(['admin_email.mailer' => 'array', 'admin_email.per_minute' => 2]);
});

function adminEmailUser(array $attributes = []): User
{
    return User::factory()->create(['avatar' => 'demo/default.png'] + $attributes);
}

describe('armado del envío', function (): void {
    beforeEach(fn () => Queue::fake());

    it('crea un destinatario por email, une usuario y contacto con el mismo email y encola el envío', function (): void {
        $user = adminEmailUser(['email' => 'Ana.Test@example.com', 'name' => 'Ana', 'agency' => 'Ana Propiedades']);
        $sameAsUser = EmailContact::factory()->create(['email' => 'ana.test@example.com']);
        $contact = EmailContact::factory()->create(['email' => 'pedro.test@example.com', 'name' => 'Pedro']);
        $template = EmailTemplate::factory()->create();

        $emailSend = app(AdminEmailService::class)->send($template, [$user->id], [$sameAsUser->id, $contact->id]);

        expect($emailSend->subject)->toBe($template->subject)
            ->and($emailSend->body)->toBe($template->body)
            ->and($emailSend->recipients)->toHaveCount(2);

        expect($emailSend->recipients->firstWhere('email', 'ana.test@example.com'))
            ->user_id->toBe($user->id)
            ->email_contact_id->toBe($sameAsUser->id)
            ->name->toBe('Ana')
            ->status->toBe(EmailSendRecipient::STATUS_PENDING);

        Queue::assertPushed(SendAdminEmailBatchJob::class, fn (SendAdminEmailBatchJob $job): bool => $job->emailSendId === $emailSend->id);
    });

    it('excluye a quienes ya recibieron el mensaje, salvo que se desactive la exclusión', function (): void {
        $first = EmailContact::factory()->create();
        $second = EmailContact::factory()->create();
        $template = EmailTemplate::factory()->create();
        $service = app(AdminEmailService::class);

        $service->send($template, [], [$first->id]);

        expect($service->summarize($template, [], [$first->id, $second->id]))
            ->toMatchArray(['to_send' => 1, 'already_sent' => 1]);

        $secondSend = $service->send($template, [], [$first->id, $second->id]);
        expect($secondSend->recipients->pluck('email')->all())->toBe([$second->email]);

        // Otro mensaje sí le llega
        expect($service->summarize(EmailTemplate::factory()->create(), [], [$first->id]))->toMatchArray(['to_send' => 1]);

        // Sin exclusión vuelve a enviarse
        expect($service->summarize($template, [], [$first->id], excludeAlreadySent: false))->toMatchArray(['to_send' => 1, 'already_sent' => 0]);
    });

    it('registra como omitidos a los dados de baja y no crea el envío si no queda nadie', function (): void {
        $contact = EmailContact::factory()->create();
        EmailSuppression::suppress(strtoupper($contact->email));
        $template = EmailTemplate::factory()->create();

        expect(app(AdminEmailService::class)->summarize($template, [], [$contact->id]))
            ->toMatchArray(['to_send' => 0, 'suppressed' => 1]);

        expect(app(AdminEmailService::class)->send($template, [], [$contact->id]))->toBeNull();
        Queue::assertNothingPushed();

        $other = EmailContact::factory()->create();
        $emailSend = app(AdminEmailService::class)->send($template, [], [$contact->id, $other->id]);

        expect($emailSend->recipients->firstWhere('email', $contact->email))
            ->status->toBe(EmailSendRecipient::STATUS_SKIPPED)
            ->error_message->toBe('Dado de baja');
    });
});

describe('envío por lotes', function (): void {
    it('envía de a per_minute, se vuelve a encolar con demora y termina el envío', function (): void {
        Queue::fake();
        Mail::fake();

        $contacts = EmailContact::factory()->count(3)->create();
        $emailSend = app(AdminEmailService::class)->send(EmailTemplate::factory()->create(), [], $contacts->modelKeys());

        (new SendAdminEmailBatchJob($emailSend->id))->handle(app(AdminEmailService::class));

        Mail::assertSentCount(2);
        expect($emailSend->recipients()->where('status', EmailSendRecipient::STATUS_SENT)->count())->toBe(2)
            ->and($emailSend->fresh()->status)->toBe(EmailSend::STATUS_SENDING);
        Queue::assertPushed(SendAdminEmailBatchJob::class, fn (SendAdminEmailBatchJob $job): bool => $job->delay !== null);

        (new SendAdminEmailBatchJob($emailSend->id))->handle(app(AdminEmailService::class));

        Mail::assertSentCount(3);
        expect($emailSend->fresh())
            ->status->toBe(EmailSend::STATUS_COMPLETED)
            ->finished_at->not->toBeNull();
    });

    it('marca como fallido al destinatario si el servidor de correo falla y permite reintentarlo', function (): void {
        Queue::fake();
        config([
            'mail.mailers.broken' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2],
            'admin_email.mailer' => 'broken',
        ]);

        $emailSend = app(AdminEmailService::class)->send(EmailTemplate::factory()->create(), [], [EmailContact::factory()->create()->id]);

        (new SendAdminEmailBatchJob($emailSend->id))->handle(app(AdminEmailService::class));

        $recipient = $emailSend->recipients()->sole();
        expect($recipient->status)->toBe(EmailSendRecipient::STATUS_FAILED)
            ->and($recipient->error_message)->not->toBeEmpty()
            ->and($emailSend->fresh()->status)->toBe(EmailSend::STATUS_COMPLETED);

        expect(app(AdminEmailService::class)->retryFailed($emailSend))->toBe(1)
            ->and($recipient->fresh()->status)->toBe(EmailSendRecipient::STATUS_PENDING)
            ->and($emailSend->fresh()->status)->toBe(EmailSend::STATUS_SENDING);
    });

    it('omite al destinatario que se dio de baja después de armado el envío', function (): void {
        Queue::fake();
        Mail::fake();

        $contact = EmailContact::factory()->create();
        $emailSend = app(AdminEmailService::class)->send(EmailTemplate::factory()->create(), [], [$contact->id]);
        EmailSuppression::suppress($contact->email);

        (new SendAdminEmailBatchJob($emailSend->id))->handle(app(AdminEmailService::class));

        Mail::assertNothingSent();
        expect($emailSend->recipients()->sole()->status)->toBe(EmailSendRecipient::STATUS_SKIPPED);
    });
});

describe('correo', function (): void {
    it('reemplaza las variables escapando el HTML y agrega el link de baja', function (): void {
        Queue::fake();
        $template = EmailTemplate::factory()->create([
            'subject' => 'Hola {nombre}',
            'body' => '<p>Hola {nombre} de {inmobiliaria} ({email})</p><script>alert(1)</script>',
        ]);
        $contact = EmailContact::factory()->create(['name' => null, 'agency' => 'Casa <Sur>']);
        $emailSend = app(AdminEmailService::class)->send($template, [], [$contact->id]);

        $mail = AdminMessageMail::forRecipient($emailSend->recipients()->sole());
        $html = $mail->render();

        expect($mail->envelope()->subject)->toBe('Hola Casa <Sur>')
            ->and($html)->toContain('Hola Casa &lt;Sur&gt; de Casa &lt;Sur&gt; (')
            ->and($html)->not->toContain('<script>')
            ->and($html)->toContain('/email/unsubscribe/')
            ->and($mail->headers()->text)->toHaveKey('List-Unsubscribe');
    });

    it('sale por el mailer configurado con la casilla de contacto como remitente', function (): void {
        expect(config('mail.mailers.admin.transport'))->toBe('smtp')
            ->and(config('mail.mailers.admin'))->toHaveKeys(['from', 'reply_to']);
    });
});

describe('baja', function (): void {
    beforeEach(function (): void {
        Queue::fake();
        $this->contact = EmailContact::factory()->create();
        $emailSend = app(AdminEmailService::class)->send(EmailTemplate::factory()->create(), [], [$this->contact->id]);
        $this->recipient = $emailSend->recipients()->sole();
        $this->url = URL::signedRoute('email.unsubscribe', ['recipient' => $this->recipient->id]);
    });

    it('el GET muestra la confirmación sin dar de baja', function (): void {
        $this->get($this->url)->assertSuccessful()->assertSee($this->contact->email);

        expect(EmailSuppression::isSuppressed($this->contact->email))->toBeFalse();
    });

    it('el POST da de baja', function (): void {
        $this->post($this->url)->assertSuccessful();

        expect(EmailSuppression::isSuppressed($this->contact->email))->toBeTrue();
    });

    it('acepta la baja en un clic del cliente de correo', function (): void {
        $this->post($this->url, ['List-Unsubscribe' => 'One-Click'])->assertNoContent();

        expect(EmailSuppression::isSuppressed($this->contact->email))->toBeTrue();
    });

    it('rechaza links sin firma', function (): void {
        $this->get('/email/unsubscribe/' . $this->recipient->id)->assertForbidden();
        $this->post('/email/unsubscribe/' . $this->recipient->id)->assertForbidden();

        expect(EmailSuppression::isSuppressed($this->contact->email))->toBeFalse();
    });
});

describe('contactos', function (): void {
    it('guarda el email en minúsculas y se vincula al usuario con el mismo email', function (): void {
        $user = adminEmailUser(['email' => 'Maria.Contacto@example.com']);

        $contact = EmailContact::factory()->create(['email' => ' MARIA.contacto@example.com ']);

        expect($contact->email)->toBe('maria.contacto@example.com')
            ->and($contact->user_id)->toBe($user->id);
    });

    it('vincula el contacto cuando se registra con su email', function (): void {
        $contact = EmailContact::factory()->create(['email' => 'nuevo.registro@example.com']);
        expect($contact->user_id)->toBeNull();

        $user = adminEmailUser(['email' => 'nuevo.registro@example.com']);
        (new LinkEmailContactToUser)->handle(new Registered($user));

        expect($contact->fresh()->user_id)->toBe($user->id);
    });
});

describe('panel admin', function (): void {
    beforeEach(function (): void {
        Queue::fake();
        $this->admin = adminEmailUser();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->actingAs($this->admin);
    });

    it('carga las páginas de mensajes, contactos y envíos', function (string $url): void {
        $this->get($url)->assertOk();
    })->with(['/admin/email-templates', '/admin/email-templates/create', '/admin/email-contacts', '/admin/email-contacts/create', '/admin/email-sends', '/admin/email-suppressions']);

    it('muestra Mensajes, Contactos y Envíos en el menú del admin', function (): void {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Comunicaciones')
            ->assertSee('/admin/email-templates', escape: false)
            ->assertSee('/admin/email-contacts', escape: false)
            ->assertSee('/admin/email-sends', escape: false);
    });

    it('crea un mensaje con el admin como autor', function (): void {
        Livewire::test(CreateEmailTemplate::class)
            ->fillForm([
                'name' => 'Presentación test',
                'subject' => 'Hola {nombre}',
                // El RichEditor guarda HTML pero en el formulario su estado es JSON de TipTap
                'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hola {nombre}']]]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(EmailTemplate::where('name', 'Presentación test')->sole())
            ->created_by->toBe($this->admin->id)
            ->body->toBe('<p>Hola {nombre}</p>');
    });

    it('crea un contacto y valida que tenga nombre o inmobiliaria', function (): void {
        Livewire::test(CreateEmailContact::class)
            ->fillForm(['email' => 'sin.nombre@example.com'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        Livewire::test(CreateEmailContact::class)
            ->fillForm(['agency' => 'Inmobiliaria Test', 'email' => 'Inmo.Test@Example.com', 'country' => 'Chile', 'whatsapp' => '+56911112222'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(EmailContact::where('email', 'inmo.test@example.com')->sole()->country)->toBe('Chile');
    });

    it('envía un mensaje desde su ficha a usuarios y contactos', function (): void {
        $template = EmailTemplate::factory()->create();
        $user = adminEmailUser();
        $contact = EmailContact::factory()->create();

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getKey()])
            ->callAction('sendEmail', data: ['user_ids' => [$user->id], 'contact_ids' => [$contact->id], 'exclude_already_sent' => true])
            ->assertHasNoActionErrors();

        expect($template->sends()->sole()->recipients()->pluck('email')->sort()->values()->all())
            ->toBe(collect([strtolower($user->email), $contact->email])->sort()->values()->all());
    });

    it('envía un mensaje a los contactos y usuarios seleccionados en la tabla', function (): void {
        $template = EmailTemplate::factory()->create();
        $contacts = EmailContact::factory()->count(2)->create();
        $user = adminEmailUser();

        Livewire::test(ListEmailContacts::class)
            ->callTableBulkAction('sendEmail', $contacts, data: ['email_template_id' => $template->id, 'exclude_already_sent' => true])
            ->assertHasNoTableBulkActionErrors();

        Livewire::test(ListUserManagement::class)
            ->callTableBulkAction('sendEmail', [$user], data: ['email_template_id' => $template->id, 'exclude_already_sent' => true])
            ->assertHasNoTableBulkActionErrors();

        expect($template->sends()->count())->toBe(2)
            ->and($template->recipients()->count())->toBe(3);
    });

    it('envía una prueba al admin sin registrarla', function (): void {
        Mail::fake();
        $template = EmailTemplate::factory()->create();

        Livewire::test(ListEmailTemplates::class)
            ->callTableAction('sendTestEmail', $template);

        Mail::assertSent(AdminMessageMail::class, fn (AdminMessageMail $mail): bool => $mail->hasTo($this->admin->email));
        expect($template->sends()->count())->toBe(0);
    });

    it('muestra el historial de correos en la ficha del usuario y del contacto', function (): void {
        $user = adminEmailUser();
        $contact = EmailContact::factory()->create();
        $template = EmailTemplate::factory()->create(['name' => 'Historial test']);
        app(AdminEmailService::class)->send($template, [$user->id], [$contact->id]);

        $this->get('/admin/user-management/' . $user->id)->assertOk()->assertSee('Correos enviados');
        $this->get('/admin/email-contacts/' . $contact->id . '/edit')->assertOk();

        // Con un solo relation manager Filament lo carga en diferido: se prueba el componente
        Livewire::test(EmailRecipientsRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => EditEmailContact::class])
            ->assertCanSeeTableRecords($contact->emailRecipients)
            ->assertSee('Historial test');
    });

    it('reintenta los fallidos desde la ficha del envío', function (): void {
        $emailSend = app(AdminEmailService::class)->send(EmailTemplate::factory()->create(), [], [EmailContact::factory()->create()->id]);
        $emailSend->recipients()->update(['status' => EmailSendRecipient::STATUS_FAILED, 'error_message' => 'timeout']);

        Livewire::test(ViewEmailSend::class, ['record' => $emailSend->getKey()])
            ->callAction('retryFailed');

        expect($emailSend->recipients()->sole()->status)->toBe(EmailSendRecipient::STATUS_PENDING);
        $this->get('/admin/email-sends/' . $emailSend->id)->assertOk();
    });
});

describe('bajas cargadas a mano', function (): void {
    beforeEach(fn () => config(['mail.mailers.admin.from.address' => 'contacto@bienesonline.ai', 'app.url' => 'https://bienesonline.ai']));

    it('toma emails sueltos separados por líneas, comas o espacios', function (): void {
        $parsed = app(SuppressionListParser::class)->parse("Uno.Test@Gmail.com, dos.test@hotmail.com\n tres.test@yahoo.com; uno.test@gmail.com juan@gmail");

        expect($parsed['emails'])->toBe(['uno.test@gmail.com', 'dos.test@hotmail.com', 'tres.test@yahoo.com'])
            ->and($parsed['invalid'])->toBe(['juan@gmail']);
    });

    it('toma del rebote solo las direcciones que fallaron, no las propias ni los avisos temporales', function (): void {
        $bounce = <<<'TXT'
            From: Mail Delivery System <Mailer-Daemon@host.bienesonline.net>
            To: contacto@bienesonline.ai
            Message-ID: <a1b2c3@bienesonline.ai>

              axiacampestrematutino3@gmail.com
                SMTP error from remote mail server after RCPT TO:<axiacampestrematutino3@gmail.com>:
                550-5.1.1 The email account that you tried to reach does not exist.
            Reporting-MTA: dns; host.bienesonline.net

            Action: failed
            Final-Recipient: rfc822;axiacampestrematutino3@gmail.com
            Status: 5.0.0
            Diagnostic-Code: smtp; 550-5.1.1 The email account that you tried to reach does not exist.

            Action: failed
            Final-Recipient: rfc822;inmobahiavallarta@gmail.com
            Status: 5.0.0

            Action: delayed
            Final-Recipient: rfc822;casilla.llena@gmail.com
            Status: 4.2.2
            TXT;

        $parsed = app(SuppressionListParser::class)->parse($bounce);

        expect($parsed['emails'])->toBe(['axiacampestrematutino3@gmail.com', 'inmobahiavallarta@gmail.com'])
            ->and($parsed['temporary'])->toBe(['casilla.llena@gmail.com']);
    });

    it('no devuelve direcciones del dominio propio ni del servidor de correo en una lista suelta', function (): void {
        $parsed = app(SuppressionListParser::class)->parse('contacto@bienesonline.ai mailer-daemon@host.example.net abc@mail.bienesonline.ai otro.test@gmail.com');

        expect($parsed['emails'])->toBe(['otro.test@gmail.com']);
    });

    it('da de baja desde el admin y el envío las saltea', function (): void {
        Queue::fake();
        $admin = adminEmailUser();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        $contact = EmailContact::factory()->create(['email' => 'rebote.test@gmail.com']);
        EmailSuppression::suppress('ya.estaba.test@gmail.com');

        Livewire::test(ListEmailSuppressions::class)
            ->callAction('addSuppressions', ['text' => "Rebote.Test@gmail.com\nya.estaba.test@gmail.com", 'reason' => EmailSuppression::REASON_BOUNCED])
            ->assertHasNoActionErrors()
            ->assertNotified('Bajas agregadas');

        expect(EmailSuppression::where('email', 'rebote.test@gmail.com')->value('reason'))->toBe(EmailSuppression::REASON_BOUNCED)
            ->and(EmailSuppression::where('email', 'ya.estaba.test@gmail.com')->value('reason'))->toBe(EmailSuppression::REASON_UNSUBSCRIBED)
            ->and(app(AdminEmailService::class)->summarize(EmailTemplate::factory()->create(), [], [$contact->id]))->toMatchArray(['to_send' => 0, 'suppressed' => 1]);

        $suppression = EmailSuppression::where('email', 'rebote.test@gmail.com')->sole();
        Livewire::test(ListEmailSuppressions::class)
            ->assertCanSeeTableRecords([$suppression])
            ->callTableAction('delete', $suppression);

        expect(EmailSuppression::isSuppressed('rebote.test@gmail.com'))->toBeFalse();
    });
});
