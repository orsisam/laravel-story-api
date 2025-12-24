# 📚 Story App SaaS - Livecode Guide

## JagoFlutter Academy 2026

---

## Overview Project

**Backend**: Laravel 12 API dengan Sanctum Auth
**Frontend**: Flutter dengan BLoC Pattern

### Fitur:

-   ✅ Register & Login
-   ✅ CRUD Story dengan Image Upload
-   ✅ Profile User
-   ✅ Logout

### Tech Stack Flutter:

-   `http` - HTTP Client
-   `flutter_bloc` - State Management
-   `dartz` - Functional Programming (Either)
-   `equatable` - Value Equality
-   `shared_preferences` - Local Storage
-   `image_picker` - Pick Image

---

# PART 1: LARAVEL BACKEND API

## Step 1: Create Laravel Project

```bash
composer create-project laravel/laravel story-api
cd story-api
```

## Step 2: Setup Database

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=story_saas
DB_USERNAME=root
DB_PASSWORD=
```

## Step 3: Install Sanctum

```bash
php artisan install:api
```

## Step 4: Create Migration Story

```bash
php artisan make:migration create_stories_table
```

Edit migration `database/migrations/xxxx_create_stories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
```

## Step 5: Run Migration

```bash
php artisan migrate
```

## Step 6: Create Model Story

```bash
php artisan make:model Story
```

Edit `app/Models/Story.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Story extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'image',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return url('storage/' . $this->image);
        }
        return null;
    }

    protected $appends = ['image_url'];
}
```

## Step 7: Update User Model

Edit `app/Models/User.php`, tambahkan:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// Di dalam class User:
public function stories(): HasMany
{
    return $this->hasMany(Story::class);
}
```

## Step 8: Create AuthController

```bash
php artisan make:controller Api/AuthController
```

Edit `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Register berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user(),
        ]);
    }
}
```

## Step 9: Create StoryController

```bash
php artisan make:controller Api/StoryController
```

Edit `app/Http/Controllers/Api/StoryController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        $stories = Story::with('user')
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $stories,
        ]);
    }

    public function myStories(Request $request)
    {
        $stories = $request->user()
            ->stories()
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $stories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->content,
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('stories', 'public');
        }

        $story = Story::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil dibuat',
            'data' => $story->load('user'),
        ], 201);
    }

    public function show(Story $story)
    {
        return response()->json([
            'status' => true,
            'data' => $story->load('user'),
        ]);
    }

    public function update(Request $request, Story $story)
    {
        // Check ownership
        if ($story->user_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'title' => $request->title,
            'content' => $request->content,
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($story->image) {
                Storage::disk('public')->delete($story->image);
            }
            $data['image'] = $request->file('image')->store('stories', 'public');
        }

        $story->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil diupdate',
            'data' => $story->fresh()->load('user'),
        ]);
    }

    public function destroy(Request $request, Story $story)
    {
        // Check ownership
        if ($story->user_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete image
        if ($story->image) {
            Storage::disk('public')->delete($story->image);
        }

        $story->delete();

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil dihapus',
        ]);
    }
}
```

## Step 10: Setup Routes

Edit `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoryController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Stories
    Route::get('/stories', [StoryController::class, 'index']);
    Route::get('/my-stories', [StoryController::class, 'myStories']);
    Route::post('/stories', [StoryController::class, 'store']);
    Route::get('/stories/{story}', [StoryController::class, 'show']);
    Route::post('/stories/{story}', [StoryController::class, 'update']); // POST untuk multipart
    Route::delete('/stories/{story}', [StoryController::class, 'destroy']);
});
```

## Step 11: Storage Link

```bash
php artisan storage:link
```

## Step 12: CORS Config (untuk Flutter)

Edit `config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],
'allowed_origins' => ['*'],
```

## Step 13: Run Server

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

---

# PART 2: FLUTTER FRONTEND

## Step 1: Create Flutter Project

```bash
flutter create story_app
cd story_app
```

## Step 2: Add Dependencies

Edit `pubspec.yaml`:

```yaml
dependencies:
    flutter:
        sdk: flutter
    http: ^1.2.0
    flutter_bloc: ^8.1.6
    dartz: ^0.10.1
    equatable: ^2.0.5
    shared_preferences: ^2.3.2
    image_picker: ^1.1.2
    cached_network_image: ^3.4.1
```

```bash
flutter pub get
```

## Step 3: Project Structure

```
lib/
├── main.dart
├── core/
│   ├── constants/
│   │   └── api_constants.dart
│   ├── error/
│   │   └── failures.dart
│   └── network/
│       └── api_client.dart
├── data/
│   ├── models/
│   │   ├── user_model.dart
│   │   └── story_model.dart
│   ├── datasources/
│   │   ├── auth_remote_datasource.dart
│   │   └── story_remote_datasource.dart
│   └── repositories/
│       ├── auth_repository.dart
│       └── story_repository.dart
├── presentation/
│   ├── blocs/
│   │   ├── auth/
│   │   │   ├── auth_bloc.dart
│   │   │   ├── auth_event.dart
│   │   │   └── auth_state.dart
│   │   └── story/
│   │       ├── story_bloc.dart
│   │       ├── story_event.dart
│   │       └── story_state.dart
│   └── pages/
│       ├── splash_page.dart
│       ├── login_page.dart
│       ├── register_page.dart
│       ├── home_page.dart
│       ├── story_detail_page.dart
│       ├── add_story_page.dart
│       ├── edit_story_page.dart
│       └── profile_page.dart
```

---

# PART 3: FLUTTER CODE FILES

(Lihat file-file terpisah untuk kode lengkap)

---

# API ENDPOINTS REFERENCE

| Method | Endpoint          | Auth | Description        |
| ------ | ----------------- | ---- | ------------------ |
| POST   | /api/register     | ❌   | Register user baru |
| POST   | /api/login        | ❌   | Login user         |
| POST   | /api/logout       | ✅   | Logout user        |
| GET    | /api/profile      | ✅   | Get profile user   |
| GET    | /api/stories      | ✅   | Get all stories    |
| GET    | /api/my-stories   | ✅   | Get my stories     |
| POST   | /api/stories      | ✅   | Create story       |
| GET    | /api/stories/{id} | ✅   | Get story detail   |
| POST   | /api/stories/{id} | ✅   | Update story       |
| DELETE | /api/stories/{id} | ✅   | Delete story       |

---

# TESTING API dengan cURL

## Register

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@mail.com","password":"password","password_confirmation":"password"}'
```

## Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@mail.com","password":"password"}'
```

## Create Story dengan Image

```bash
curl -X POST http://localhost:8000/api/stories \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "title=My Story" \
  -F "content=This is my story content" \
  -F "image=@/path/to/image.jpg"
```

---

Selamat Livecode! 🎉
