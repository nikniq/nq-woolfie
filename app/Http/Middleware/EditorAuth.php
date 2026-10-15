<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EditorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('game.editor_password') === '') {
            abort(403, 'The room editor is disabled. Set EDITOR_PASSWORD in .env to enable it.');
        }
        if (! $request->session()->get('editor_ok')) {
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('editor.login');
        }

        return $next($request);
    }
}
