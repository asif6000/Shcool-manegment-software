<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function __construct(private readonly StudentImportService $import) {}

    public function form(): View
    {
        $this->requirePermission('students.create');

        return view('students.import', [
            'fields' => $this->import->fields(),
            'maxRows' => StudentImportService::MAX_ROWS,
        ]);
    }

    public function template(): StreamedResponse
    {
        $this->requirePermission('students.create');

        return $this->import->template();
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $this->requirePermission('students.create');

        $validated = $request->validate([
            'csv' => ['required', 'file', 'max:4096', 'mimes:csv,txt,tsv'],
        ], [
            'csv.required' => __('Choose the sheet you want to import.'),
            'csv.mimes' => __('Only a .csv, .txt or .tsv sheet can be imported.'),
            'csv.max' => __('The sheet must stay under 4 MB.'),
        ]);

        $previous = $request->session()->get('student_import.token');

        if (is_string($previous)) {
            $this->import->forget($previous);
        }

        /** @var UploadedFile $file */
        $file = $validated['csv'];

        try {
            $token = $this->import->storeUpload($file);
            $batch = $this->import->analyse($token);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            return back()->withInput()->withErrors(['csv' => $exception->getMessage()]);
        }

        $request->session()->put('student_import.token', $token);

        return view('students.import-preview', [
            'token' => $token,
            'batch' => $batch,
            'fileName' => $file->getClientOriginalName(),
            'classNames' => SchoolClass::query()->pluck('name', 'id'),
            'sectionNames' => Section::query()->pluck('name', 'id'),
            'sessionNames' => AcademicSession::query()->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('students.create');

        $validated = $request->validate([
            'token' => ['required', 'string', 'regex:/^[a-f0-9\-]{36}\.csv$/'],
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => __('Tick the confirmation box before importing real student records.'),
        ]);

        try {
            $result = $this->import->commit($request->user(), $validated['token']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }

            return redirect()
                ->route('student_import.index')
                ->withErrors(['csv' => __('That preview has expired. Upload the sheet again.')]);
        }

        $request->session()->forget('student_import.token');

        $message = __(':imported students were imported.', ['imported' => $result['imported']]);

        if ($result['failed'] > 0) {
            $message .= ' '.__(':failed rows were skipped.', ['failed' => $result['failed']]);

            return redirect()
                ->route('students.index')
                ->with('success', $message)
                ->with('warning', collect($result['failures'])
                    ->take(15)
                    ->map(fn (array $failure): string => __('Line :line: :reason', [
                        'line' => $failure['line'],
                        'reason' => $failure['reason'],
                    ]))
                    ->values()
                    ->all());
        }

        return redirect()->route('students.index')->with('success', $message);
    }
}
