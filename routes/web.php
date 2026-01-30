<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Wave\Facades\Wave;
use App\Http\Controllers\PropertySearchController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyRequestController;
use App\Http\Controllers\PropertyMatchController;
use App\Http\Controllers\PropertyMessageController;
use App\Http\Controllers\RequestSearchController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ============================================================================
// 0. SITEMAP ROUTES (SEO - Sin prefijo de locale)
// ============================================================================
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-properties-{locale}.xml', [SitemapController::class, 'properties'])
    ->where('locale', 'es|en')
    ->name('sitemap.properties');

// ============================================================================
// 1. REDIRECT RAÍZ AL LOCALE POR DEFECTO
// ============================================================================
Route::get('/', function () {
    $locale = session('locale', config('locales.default', 'es'));
    return redirect("/{$locale}");
});

// ============================================================================
// 2. RUTAS PÚBLICAS CON PREFIJO {locale} (para SEO)
// ============================================================================
Route::prefix('{locale}')->where(['locale' => 'es|en'])->group(function () {
    
    // Home route
    Route::get('/', function () {
        $seo = [
            'title' => setting('site.title', 'Raxta - Plataforma Inmobiliaria Inteligente'),
            'description' => setting('site.description', 'Conectamos propiedades con compradores y agentes de forma inteligente.'),
            'image' => url('/og_image.png'),
            'type' => 'website'
        ];
        return view('theme::pages.index', compact('seo'));
    })->name('home');

    // Property Search (pública)
    Route::get('/search-properties', [PropertySearchController::class, 'index'])->name('property.search');
    
    // Property Detail (pública) - Con slug opcional para SEO
    Route::get('/property/{id}/{slug?}', [PropertyController::class, 'show'])->name('property.show');
    
    // Property Message (requiere auth pero es parte de la vista pública)
    Route::post('/property/{id}/message', [PropertyController::class, 'sendMessage'])->name('property.message')->middleware('auth');

    // Request Search (pública)
    Route::get('/search-requests', [RequestSearchController::class, 'index'])->name('requests.search');
});

// ============================================================================
// 3. RUTA PARA CAMBIAR LOCALE (guarda en sesión)
// ============================================================================
Route::post('/locale/switch', function(Request $request) {
    $locale = $request->input('locale', 'es');
    if (in_array($locale, ['es', 'en'])) {
        session(['locale' => $locale]);
    }
    return back();
})->name('locale.switch');

// ============================================================================
// 3.5 RUTAS DE AUTENTICACIÓN (sobrescribir DevDojo Auth)
// ============================================================================
Route::post('/auth/logout', \App\Http\Controllers\Auth\LogoutController::class)->name('logout');
Route::get('/auth/logout', \App\Http\Controllers\Auth\LogoutController::class)->name('logout.get');

// ============================================================================
// 4. RUTAS PRIVADAS SIN PREFIJO (usan locale de sesión)
// ============================================================================
Route::middleware('auth')->group(function () {
    
    // Dashboard principal
    Route::get('/dashboard', function () {
        $userListings = \App\Models\PropertyListing::where('user_id', auth()->id())->active()->count();
        $userRequests = \App\Models\PropertyRequest::where('user_id', auth()->id())->active()->count();
        $unreadMessages = \App\Models\PropertyMessage::whereHas('propertyListing', function($query) {
            $query->where('user_id', auth()->id());
        })->where('is_read', false)->count();
        
        $matchingService = app(\App\Services\PropertyMatchingService::class);
        $recentListings = \App\Models\PropertyListing::where('user_id', auth()->id())->active()->take(3)->get();
        $totalMatches = 0;
        foreach ($recentListings as $listing) {
            $totalMatches += $matchingService->findMatchesForListing($listing, 5)->count();
        }
        
        return view('theme::pages.dashboard.index', compact('userListings', 'userRequests', 'unreadMessages', 'totalMatches'));
    })->name('dashboard');
    
    // Terms acceptance
    Route::post('/dashboard/terms/accept', [TermsController::class, 'accept'])->name('terms.accept');

    // Property Requests (Dashboard)
    Route::prefix('dashboard/requests')->name('dashboard.requests.')->group(function () {
        Route::get('/', [PropertyRequestController::class, 'index'])->name('index');
        Route::get('/create', [PropertyRequestController::class, 'create'])->name('create');
        Route::post('/', [PropertyRequestController::class, 'store'])->name('store');
        Route::get('/{propertyRequest}', [PropertyRequestController::class, 'show'])->name('show');
        Route::get('/{propertyRequest}/edit', [PropertyRequestController::class, 'edit'])->name('edit');
        Route::put('/{propertyRequest}', [PropertyRequestController::class, 'update'])->name('update');
        Route::delete('/{propertyRequest}', [PropertyRequestController::class, 'destroy'])->name('destroy');
        Route::post('/{propertyRequest}/toggle-active', [PropertyRequestController::class, 'toggleActive'])->name('toggle-active');
    });

    // AJAX routes for locations
    Route::get('/api/states', [PropertyRequestController::class, 'getStates'])->name('api.states');
    Route::get('/api/cities', [PropertyRequestController::class, 'getCities'])->name('api.cities');

    // Property Matches (Dashboard)
    Route::prefix('dashboard/matches')->name('dashboard.matches.')->group(function () {
        Route::get('/', [PropertyMatchController::class, 'index'])->name('index');
        Route::get('/listing/{listing}', [PropertyMatchController::class, 'show'])->name('show');
    });

    // Property Messages (Dashboard)
    Route::prefix('dashboard/messages')->name('dashboard.messages.')->group(function () {
        Route::get('/', [PropertyMessageController::class, 'index'])->name('index');
        Route::get('/{id}', [PropertyMessageController::class, 'show'])->name('show');
        Route::post('/{id}/mark-read', [PropertyMessageController::class, 'markAsRead'])->name('mark-read');
        Route::post('/{id}/mark-unread', [PropertyMessageController::class, 'markAsUnread'])->name('mark-unread');
        Route::delete('/{id}', [PropertyMessageController::class, 'destroy'])->name('destroy');
    });
});

// ============================================================================
// 5. WAVE ROUTES (maneja sus propias rutas - incluye rutas de páginas SIN locale)
// ============================================================================
Wave::routes();