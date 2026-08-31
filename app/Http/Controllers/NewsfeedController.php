<?php

namespace App\Http\Controllers;

use App\Models\Newsfeed;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NewsfeedController extends Controller
{
  public function store(Request $request)
  {
    Newsfeed::create($this->validatedData($request));

    return redirect()->route('inventory.index');
  }

  public function update(Request $request, $id)
  {
    $news = Newsfeed::findOrFail($id);
    $news->update($this->validatedData($request));

    return redirect()->route('inventory.index');
  }

  public function destroy($id)
  {
    Newsfeed::findOrFail($id)->delete();

    return redirect()->route('inventory.index');
  }

  private function validatedData(Request $request): array
  {
    $validated = $request->validate([
      'title' => [ 'required', 'string', 'max:255' ],
      'message' => [ 'required', 'string', 'max:255' ],
      'from' => [ 'nullable', 'date' ],
      'until' => [ 'nullable', 'date' ],
      'data' => [ 'sometimes', 'nullable', 'array' ],
    ]);

    if (
      !empty($validated['from']) &&
      !empty($validated['until']) &&
      CarbonImmutable::parse($validated['until'])->isBefore(CarbonImmutable::parse($validated['from']))
    ) {
      throw ValidationException::withMessages([
        'until' => 'Das Ende darf nicht vor dem Beginn liegen.',
      ]);
    }

    return $validated;
  }
}
