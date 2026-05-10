<?php

namespace App\Livewire;

use App\Models\FantreffenVipAuthor;
use App\Models\Veranstaltung;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FantreffenVipAuthors extends Component
{
    public Veranstaltung $veranstaltung;

    // Form fields
    public $name = '';

    public $pseudonym = '';

    public $is_active = true;

    public $is_tentative = false;

    public $sort_order = 0;

    // Edit mode
    public $editingId = null;

    public $showForm = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'pseudonym' => 'nullable|string|max:255',
        'is_active' => 'boolean',
        'is_tentative' => 'boolean',
        'sort_order' => 'integer|min:0',
    ];

    protected $messages = [
        'name.required' => 'Bitte gib den Namen des Autors an.',
        'name.max' => 'Der Name darf maximal 255 Zeichen lang sein.',
        'pseudonym.max' => 'Das Pseudonym darf maximal 255 Zeichen lang sein.',
        'sort_order.integer' => 'Die Sortierung muss eine ganze Zahl sein.',
        'sort_order.min' => 'Die Sortierung darf nicht negativ sein.',
    ];

    public function mount(Veranstaltung $veranstaltung): void
    {
        $this->veranstaltung = $veranstaltung;
    }

    protected function cacheKey(): string
    {
        return 'fantreffen_vip_authors_'.$this->veranstaltung->id;
    }

    protected function query()
    {
        return FantreffenVipAuthor::query()->where('veranstaltung_id', $this->veranstaltung->id);
    }

    protected function findAuthor(int $id): FantreffenVipAuthor
    {
        return $this->query()->findOrFail($id);
    }

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
        $author = $this->findAuthor($id);

        $this->editingId = $author->id;
        $this->name = $author->name;
        $this->pseudonym = $author->pseudonym ?? '';
        $this->is_active = $author->is_active;
        $this->is_tentative = (bool) $author->is_tentative;
        $this->sort_order = $author->sort_order;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingId) {
            $author = $this->findAuthor($this->editingId);
            $author->update([
                'name' => $this->name,
                'pseudonym' => $this->pseudonym ?: null,
                'is_active' => $this->is_active,
                'is_tentative' => $this->is_tentative,
                'sort_order' => $this->sort_order,
            ]);
            session()->flash('success', 'Autor erfolgreich aktualisiert.');
        } else {
            FantreffenVipAuthor::create([
                'veranstaltung_id' => $this->veranstaltung->id,
                'name' => $this->name,
                'pseudonym' => $this->pseudonym ?: null,
                'is_active' => $this->is_active,
                'is_tentative' => $this->is_tentative,
                'sort_order' => $this->sort_order,
            ]);
            session()->flash('success', 'Autor erfolgreich hinzugefügt.');
        }

        Cache::forget($this->cacheKey());
        $this->closeForm();
    }

    public function toggleActive($id)
    {
        $author = $this->findAuthor($id);
        $author->is_active = ! $author->is_active;
        $author->save();

        Cache::forget($this->cacheKey());

        $status = $author->is_active ? 'aktiviert' : 'deaktiviert';
        session()->flash('success', "Autor \"{$author->name}\" wurde {$status}.");
    }

    public function delete($id)
    {
        $author = $this->findAuthor($id);
        $name = $author->name;

        DB::transaction(function () use ($author) {
            $author->delete();
            $this->recompactSortOrder();
        });

        Cache::forget($this->cacheKey());

        session()->flash('success', "Autor \"{$name}\" wurde gelöscht.");
    }

    public function moveUp($id)
    {
        $author = $this->findAuthor($id);
        $currentOrder = $author->sort_order;

        if ($currentOrder <= 0) {
            return;
        }

        $authorAbove = $this->query()->where('sort_order', $currentOrder - 1)->first();

        if (! $authorAbove) {
            return;
        }

        DB::transaction(function () use ($author, $authorAbove, $currentOrder) {
            $authorAbove->sort_order = $currentOrder;
            $authorAbove->save();

            $author->sort_order = $currentOrder - 1;
            $author->save();
        });

        Cache::forget($this->cacheKey());
    }

    public function moveDown($id)
    {
        $author = $this->findAuthor($id);
        $currentOrder = $author->sort_order;

        $authorBelow = $this->query()->where('sort_order', $currentOrder + 1)->first();

        if (! $authorBelow) {
            return;
        }

        DB::transaction(function () use ($author, $authorBelow, $currentOrder) {
            $authorBelow->sort_order = $currentOrder;
            $authorBelow->save();

            $author->sort_order = $currentOrder + 1;
            $author->save();
        });

        Cache::forget($this->cacheKey());
    }

    protected function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->pseudonym = '';
        $this->is_active = true;
        $this->is_tentative = false;
        $this->sort_order = $this->getNextSortOrder();
        $this->resetValidation();
    }

    protected function getNextSortOrder(): int
    {
        $maxOrder = $this->query()->max('sort_order');

        return ($maxOrder ?? -1) + 1;
    }

    protected function recompactSortOrder(): void
    {
        $authors = $this->query()->orderBy('sort_order')->get(['id']);

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
    }

    public function render()
    {
        $authors = $this->query()->ordered()->get();
        $activeAuthors = $authors->where('is_active', true);

        return view('livewire.fantreffen-vip-authors', [
            'authors' => $authors,
            'activeAuthors' => $activeAuthors,
        ])->layout('layouts.admin', [
            'title' => $this->veranstaltung->titel.' - VIP-Autoren',
        ]);
    }

    public function placeholder()
    {
        return view('components.skeleton-table', ['columns' => 5, 'rows' => 6]);
    }
}
