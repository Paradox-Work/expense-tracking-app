<?php

use Illuminate\Support\Facades\Route;


Route::view('/', 'welcome')->name('home');



Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/categories', 'categories')->name('categories.index');
    Route::livewire('/budgets', 'budget-list')->name('budgets.index');
    Route::livewire('/budgets/create', 'budget-form')->name('budget.create');
    Route::livewire('/budgets/{budgetId}/edit', 'budget-form')->name('budgets.edit');
  
});

require __DIR__.'/settings.php';
