<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return StudentResource::collection(Student::paginate(20));
    }

    public function store(StoreStudentRequest $request): StudentResource
    {
        return StudentResource::make(Student::create($request->validated()));
    }

    public function show(Student $student): StudentResource
    {
        return StudentResource::make($student);
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return StudentResource::make($student);
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(['message' => 'Student deleted successfully.']);
    }

    public function uploadPhoto(UploadPhotoRequest $request, Student $student): StudentResource|JsonResponse
    {
        try {
            $student->uploadPhoto($request->file('photo'));

            return StudentResource::make($student->fresh());
        } catch (Throwable $e) {
            Log::error('Failed to upload student photo', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Photo upload failed. Please try again later.',
            ], 503);
        }
    }

    public function deletePhoto(Student $student): JsonResponse
    {
        try {
            $student->deletePhoto();

            return response()->json(['message' => 'Profile photo deleted successfully.']);
        } catch (Throwable $e) {
            Log::error('Failed to delete student photo', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Photo deletion failed. Please try again later.',
            ], 503);
        }
    }
}
