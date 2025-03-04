<?php

namespace App\Http\Controllers\Frontend;

use App\Events\TaskUpdated as EventsTaskUpdated;
use Illuminate\Http\Request;
use App\Models\Task;   
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use TMS\app\Events\TaskUpdated;

class TaskController extends Controller
{
  
//  TASK FORM RENDERING(DISPLAY) AND STORE   
    public function index()
    {   
        // For  Displaying the task Form view (frontend.task).
        // It   returns a Form view  in the browser.
        return view('frontend.task');
        // Route::get('/task',[TaskController::class,'index']); 
    }

        // Display Usres with Select2 Searchable Dropdown 
        public function getUsers(Request $request)
        {
            /*
                Fetches users based on a search query.
            */
            $search = $request->get('q');  
            /*
                It retrieves users whose names or user types match the search query.
            */
    
            $users = User::where('name', 'LIKE', "%{$search}%")
                        ->orWhere('user_type', 'LIKE', "%{$search}%")
                        ->select('id', 'name', 'user_type')  
                        ->take(10)  
                        ->get();
            /*
                It returns a JSON response with the matched users.
            */
            return response()->json($users);
        }
        // Display Usres with Select2 Searchable Dropdown

    public function store(Request $request)
    {   
        // For Creating a new task.
        // Route::post('/tasks', [TaskController::class, 'store'])->name('task.store');
        try {
            /* 
                First, the method validates the incoming request data to ensure all necessary fields
                are present and in the correct format. 
            */
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'task_description' => 'required|string',
                'assign_to' => 'required|array',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'flag' => 'required|string|max:255',
                'priority' => 'required|string',
            ]);
    
            /* 
                If validation passes, a new task is created using the Task::create() method,
                where the validated data is saved to the database.
            */
            $task = Task::create([
                'title' => $validated['title'],
                'task_description' => $validated['task_description'],
                'assign_to' => implode(',', $validated['assign_to']),
                'task_created_by' => Auth::id(),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'flag' => $validated['flag'],
                'priority' => $validated['priority'],
                'user_id' => Auth::id(),
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Task created successfully!',
                'redirect_url' => route('display')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            /*
                If validation fails, it catches the validation exception and returns an error message.
            */
            $response['status'] = 'failed';
            $response['message'] = 'All Fields Required';
            $response['errors'] = $e->errors();  
            return response()->json($response, 422);  
        } catch (\Exception $e) {
            /*
                In case of any other exception, it returns a generic error message.
            */
            $response['message'] = 'An error occurred while creating the task. Please try again later.';
            $response['status'] = "failed";
            $response['act'] = "TERMINATE";
            return response()->json($response);
        }
    }
//  TASK FORM RENDERING(DISPLAY) AND STORE    

    
 

    

// LIST AND DISPLAY 
    public function getTasksList(Request $request)
    {
        /*
            Fetches and lists tasks with pagination.
            It accepts parameters like start and length for pagination 
        */
        $start  = $request->input('start', 0); 
        $length = $request->input('length', 10); 
        $page   = ($start / $length) + 1;  
        /*
            It joins the tasks table with users for the assigned user
            and the creator of the task
        */
        $query = Task::query()
            ->leftJoin('users as assign_to_user', 'tasks.assign_to', '=', 'assign_to_user.id') 
            // Joins the users table to the tasks table using a left join 
            ->leftJoin('users as created_by_user', 'tasks.task_created_by', '=', 'created_by_user.id') 
            // The alias assign_to_user is used for the users table.
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.task_description',
                'assign_to_user.name as assign_to_name', 
                'created_by_user.name as task_created_by_name',  
                'tasks.start_date',
                'tasks.end_date',
                'tasks.flag',
                'tasks.priority',
                'tasks.status'
            ]); 
    
        $data = $query->paginate($length, ['*'], 'page', $page); 
            
        /* 
            The tasks are then paginated and formatted for display, 
            including buttons for editing, deleting, or restoring tasks depending on their status.
        */
        $tableData = $data->map(function ($item) {
            // Determine the buttons based on task status
            $buttons = '<div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary edit-task d-inline-block"
                                data-id="' . $item->id . '"
                                data-bs-toggle="modal"
                                data-bs-target="#editTaskModal">
                            Edit
                            </button>';
    
            // Show delete button if status is not 'deleted'
            if ($item->status !== 'deleted') {
                $buttons .= '<button class="btn btn-sm btn-danger delete-task d-inline-block" 
                                data-id="' . $item->id . '">
                                Delete
                            </button>';
            } else {
                // Show restore button if status is 'deleted'
                $buttons .= '<button class="btn btn-sm btn-success restore-task d-inline-block" 
                                data-id="' . $item->id . '">
                                Restore
                            </button>';
            }
    
            $buttons .= '</div>';

        // Add "View Details" button
        $viewDetailsButton = ' <button class="mb-2 btn btn-sm btn-info view-task d-inline-block" 
                                data-id="' . $item->id . '"
                                data-bs-toggle="modal"
                                data-bs-target="#viewTaskModal">
                                View Task
                            </button>';

    
            return [
                $item->id,
                $item->title,
                $item->task_description,
                $item->assign_to_name,
                $item->task_created_by_name,  
                $item->start_date,
                $item->end_date,
                $item->flag,
                $item->priority,
                $item->status,
                $viewDetailsButton . ' ' . $buttons,  
            ];
        });
    
        // The response contains the total number of tasks, filtered records, and the formatted data
        $response = [
            'recordsTotal' => Task::count(),  // Total number of records
            'recordsFiltered' => $data->total(),  // Total number of filtered records
            'data' => $tableData,  // The data to display in the table
            'status' => 'success',
        ];
        
        return response()->json($response);
    }



    public function display()
    {
        //  Displays the task table.
        $columns = [
            ['title' => '#ID', 'data' => 0], 
            ['title' => 'Title', 'data' => 1],
            ['title' => 'Task Description', 'data' => 2],
            ['title' => 'Assign To', 'data' => 3],
            ['title' => 'Task Created By ', 'data' => 4],
            ['title' => 'Start Date', 'data' => 5],
            ['title' => 'End Date', 'data' => 6],
            ['title' => 'Flag', 'data' => 7],
            ['title' => 'Priority', 'data' => 8],
            ['title' => 'Status', 'data' => 9],
            ['title' => 'Actions', 'data' => 10],
        ];
        
        /* It prepares column headers for a task table and retrieves 
            all users to be used in the frontend.
        */    
        $users = User::select('id', 'name', 'user_type')->get();
        
        // It returns the task table view with the necessary data.
        return view('frontend.task-table', compact('columns', 'users'));
    }
   
// LIST AND DISPLAY 
    

// GET AND UPDATE
    public function getTaskById($id)
    { 
        // Fetches a specific task by its ID.
        $task = Task::with('user')->findOrFail($id);
        // dd($task); 
        return response()->json([
            'data' => $task
        ]);
    }
    
    public function updateTask(Request $request)
    {
            try {
                // Find the task by ID
                $task = Task::findOrFail($request->input('id'));
        
                // Check if the user is authorized to update the task
                if ($task->task_created_by != Auth::id()) {
                    return response()->json(['status' => 'error', 'message' => 'You are not authorized to update this task']);
                }
        
                // Update the task with new values from the request
                $task->title = $request->input('title');
                $task->task_description = $request->input('task_description');
                $task->assign_to = $request->input('assign_to');
                $task->start_date = $request->input('start_date');
                $task->end_date = $request->input('end_date');
                $task->flag = $request->input('flag');
                $task->priority = $request->input('priority');
        
                // Save the updated task
                $task->save();
        
                // The TaskObserver will handle logging the activity
                return response()->json(['status' => 'success', 'message' => 'Task updated successfully']);
            } catch (\Exception $e) {
                return response()->json(['status' => 'error', 'message' => 'Failed to update task', 'error' => $e->getMessage()]);
            }
    }
    
    

// GET AND UPDATE


// DELETE AND RESTORE 
    public function deleteTask($id)
    {
        try { 
            $task = Task::findOrFail($id); 
            if ($task->task_created_by != Auth::id()) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'You are not authorized to delete this task'
                ]);
            }
            if ($task->status === 'deleted') {
                return response()->json(['status' => 'error', 'message' => 'Task is already deleted']);
            }
            $task->status = 'deleted';
            $task->save();
    
            return response()->json(['status' => 'success', 'message' => 'Task status updated to deleted']);
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            \Log::error('Error updating task status to deleted', ['error' => $e->getMessage()]);
    
            return response()->json(['status' => 'error', 'message' => 'Failed to update task status']);
        }
    }


    public function restoreTask($id)
    {
        try {
            
            $task = Task::findOrFail($id);
    
           
            if ($task->task_created_by != Auth::id()) {
                return response()->json(['status' => 'error', 'message' => 'You are not authorized to restore this task']);
            }
    
            
            $task->status = 'active';
            $task->save();
    
            return response()->json(['status' => 'success', 'message' => 'Task restored successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to restore task']);
        }
    }
        
//  DELETE AND RESTORE
    

    

}



