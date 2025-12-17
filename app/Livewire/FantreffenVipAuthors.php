<?php

namespace App\Livewire;

use App\Models\FantreffenVipAuthor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FantreffenVipAuthors extends Component
{
    // Form fields
    public $name = '';

    public $pseudonym = '';

    public $is_active = true;

    public $sort_order = 0;

    // Edit mode
    public $editingId = null;

    public $showForm = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'pseudonym' => 'nullable|string|max:255',
        'is_active' => 'boolean',
        'sort_order' => 'integer|min:0',
    ];

    protected $messages = [
        'name.required' => 'Bitte gib den Namen des Autors an.',
        'name.max' => 'Der Name darf maximal 255 Zeichen lang sein.',
        'pseudonym.max' => 'Das Pseudonym darf maximal 255 Zeichen lang sein.',
        'sort_order.integer' => 'Die Sortierung muss eine ganze Zahl sein.',
        'sort_order.min' => 'Die Sortierung darf nicht negativ sein.',
    ];

    public function openForm()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function edit($id)
    {
        $author = FantreffenVipAuthor::findOrFail($id);

        $this->editingId = $author->id;
        $this->name = $author->name;
        $this->pseudonym = $author->pseudonym ?? '';
        $this->is_active = $author->is_active;
        $this->sort_order = $author->sort_order;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingId) {
            $author = FantreffenVipAuthor::findOrFail($this->editingId);
            $author->update([
                'name' => $this->name,
                'pseudonym' => $this->pseudonym ?: null,
                'is_active' => $this->is_active,
                'sort_order' => $this->sort_order,
            ]);
            session()->flash('success', 'Autor erfolgreich aktualisiert.');
        } else {
            FantreffenVipAuthor::create([
                'name' => $this->name,
                'pseudonym' => $this->pseudonym ?: null,
                'is_active' => $this->is_active,
                'sort_order' => $this->sort_order,
            ]);
            session()->flash('success', 'Autor erfolgreich hinzugefügt.');
        }

        Cache::forget('fantreffen_vip_authors');
        $this->closeForm();
    }

    public function toggleActive($id)
    {
        $author = FantreffenVipAuthor::findOrFail($id);
        $author->is_active = ! $author->is_active;
        $author->save();

        Cache::forget('fantreffen_vip_authors');

        $status = $author->is_active ? 'aktiviert' : 'deaktiviert';
        session()->flash('success', "Autor \"{$author->name}\" wurde {$status}.");
    }

    public function delete($id)
    {
        $author = FantreffenVipAuthor::findOrFail($id);
        $name = $author->name;

        DB::transaction(function () use ($author) {
            $author->delete();
            $this->recompactSortOrder();
        });

        Cache::forget('fantreffen_vip_authors');

        session()->flash('success', "Autor \"{$name}\" wurde gelöscht.");
    }

    public function moveUp($id)
    {
        $author = FantreffenVipAuthor::findOrFail($id);
        $currentOrder = $author->sort_order;

        if ($currentOrder <= 0) {
            return;
        }

        $authorAbove = FantreffenVipAuthor::where('sort_order', $currentOrder - 1)->first();

        if (! $authorAbove) {
            return;
        }

        DB::transaction(function () use ($author, $authorAbove, $currentOrder) {
            $authorAbove->sort_order = $currentOrder;
            $authorAbove->save();

            $author->sort_order = $currentOrder - 1;
            $author->save();
        });

        Cache::forget('fantreffen_vip_authors');
    }

    public function moveDown($id)
    {
        $author = FantreffenVipAuthor::findOrFail($id);
        $currentOrder = $author->sort_order;

        $authorBelow = FantreffenVipAuthor::where('sort_order', $currentOrder + 1)->first();

        if (! $authorBelow) {
            return;
        }

        DB::transaction(function () use ($author, $authorBelow, $currentOrder) {
            $authorBelow->sort_order = $currentOrder;
            $authorBelow->save();

            $author->sort_order = $currentOrder + 1;
            $author->save();
        });

        Cache::forget('fantreffen_vip_authors');
    }

    protected function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->pseudonym = '';
        $this->is_active = true;
        $this->sort_order = $this->getNextSortOrder();
        $this->resetValidation();
    }

    protected function getNextSortOrder(): int
    {
        $maxOrder = FantreffenVipAuthor::max('sort_order');

        return ($maxOrder ?? -1) + 1;
    }

    protected function recompactSortOrder(): void
    {
        $authors = FantreffenVipAuthor::orderBy('sort_order')->get(['id']);

        if ($authors->isEmpty()) {
            return;
        }

        $cases = [];
        $bindings = [];
        $ids = [];

        foreach ($authors as $index => $author) {
            $cases[] = 'WHEN id = ? THEN ?';
            $bindings[] = $author->id;
            $bindings[] = $index;
            $ids[] = $author->id;
        }

        $casesString = implode(' ', $cases);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        DB::update("UPDATE fantreffen_vip_authors SET sort_order = CASE {$casesString} END WHERE id IN ({$placeholders})", $bindings);

        Cache::forget('fantreffen_vip_authors');
    }

    public function render()
    {
        $authors = FantreffenVipAuthor::ordered()->get();
        $activeAuthors = $authors->where('is_active', true);

        return view('livewire.fantreffen-vip-authors', [
            'authors' => $authors,
            'activeAuthors' => $activeAuthors,
        ])->layout('layouts.app', [
            'title' => 'Fantreffen 2026 - VIP-Autoren verwalten',
        ]);
    }
}
