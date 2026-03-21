<?php

use Illuminate\Support\Facades\Route;



Route::view('/', 'welcome')->name('home');



Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/categories', 'categories')->name('categories.index');
    Route::livewire('/budgets', 'budget-list')->name('budgets.index');
    Route::livewire('/budgets/create', 'budget-form')->name('budget.create');
    Route::livewire('/budgets/{budgetId}/edit', 'budget-form')->name('budgets.edit');
    Route::livewire('/expenses', 'expense-list')->name('expenses.index');
    Route::livewire('/expenses/create', 'expense-form')->name('expense.create');
    Route::livewire('/expenses/{expenseId}/edit', 'expense-form')->name('expenses.edit'); 
    Route::livewire('/recurring-expenses', 'recurring-expense')->name('recurring-expenses.index');
});

require __DIR__.'/settings.php';
