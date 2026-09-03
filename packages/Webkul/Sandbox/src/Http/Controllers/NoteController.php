<?php

namespace Webkul\Sandbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Sandbox\Repositories\NoteRepository;

class NoteController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected NoteRepository $noteRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('sandbox::notes.index', [
            'notes' => $this->noteRepository->all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse
    {
        $data = request()->validate([
            'title' => 'required|string|max:255',
            'body'  => 'nullable|string',
        ]);

        $this->noteRepository->create($data);

        session()->flash('success', trans('sandbox::app.notes.create-success'));

        return redirect()->route('admin.sandbox.notes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->noteRepository->delete($id);

        session()->flash('success', trans('sandbox::app.notes.delete-success'));

        return redirect()->route('admin.sandbox.notes.index');
    }
}
