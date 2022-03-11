<?php

namespace App\Interfaces;

use Illuminate\Http\Request;

interface ControllerInterface
{
    public function store(Request $request);

    public function index();

    public function show(string $id);

    public function update(string $id, Request $request);

    public function destroy(string $id);

}