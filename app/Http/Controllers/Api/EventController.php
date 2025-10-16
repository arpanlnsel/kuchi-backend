<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events",
     *     summary="Get all events (Public - No authentication required)",
     *     tags={"Events"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Active", "Inactive", "Cancelled", "Completed"})
     *     ),
     *     @OA\Parameter(
     *         name="EventType",
     *         in="query",
     *         description="Filter by event type based on start time",
     *         required=false,
     *         @OA\Schema(type="string", enum={"newEvent", "oldEvent"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of events"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Event::with(['user:id,name,email', 'videos']);
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by EventType based on start_time
        if ($request->filled('EventType')) {
            $now = Carbon::now();
            
            if ($request->EventType === 'newEvent') {
                // Events that haven't started yet (future events)
                $query->where('start_time', '>', $now);
            } elseif ($request->EventType === 'oldEvent') {
                // Events that have already started (past events)
                $query->where('start_time', '<=', $now);
            }
        }
        
        $events = $query->orderBy('start_time', 'desc')->get();
        
        // Add image URLs and EventType to each event
        $events->each(function ($event) {
            $event->main_image_url = $event->main_image_url;
            $event->event_images_urls = $event->event_images_urls;
            
            // Add EventType attribute based on start_time
            $now = Carbon::now();
            $event->EventType = Carbon::parse($event->start_time)->gt($now) ? 'newEvent' : 'oldEvent';
        });
        
        return response()->json([
            'success' => true,
            'total' => $events->count(),
            'filters_applied' => [
                'status' => $request->status ?? null,
                'EventType' => $request->EventType ?? null
            ],
            'data' => $events
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events",
     *     summary="Create a new event (Admin only)",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "venue", "start_time", "end_time"},
     *                 @OA\Property(property="title", type="string", example="Annual Tech Conference"),
     *                 @OA\Property(property="description", type="string", example="An event for tech enthusiasts."),
     *                 @OA\Property(property="venue", type="string", example="Dubai World Trade Center"),
     *                 @OA\Property(property="status", type="string", enum={"Active", "Inactive", "Cancelled", "Completed"}),
     *                 @OA\Property(property="start_time", type="string", format="date-time", example="2025-10-20T10:00:00"),
     *                 @OA\Property(property="end_time", type="string", format="date-time", example="2025-10-20T18:00:00"),
     *                 @OA\Property(property="show_video_details", type="boolean", example=true),
     *                 @OA\Property(property="event_location", type="string", example="Hall 3, Gate A"),
     *                 @OA\Property(property="main_image", type="string", format="binary", description="Main event image"),
     *                 @OA\Property(
     *                     property="event_images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Multiple event images"
     *                 ),
     *                 @OA\Property(
     *                     property="videos",
     *                     type="string",
     *                     description="JSON string of videos. Example: [{title: Intro, url: https://youtu.be/abc}]"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Event created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'status' => 'nullable|in:Active,Inactive,Cancelled,Completed',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'show_video_details' => 'nullable|in:0,1,true,false',
            'event_location' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'event_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'videos' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // ✅ Convert show_video_details to boolean
        $showVideoDetails = false;
        if ($request->has('show_video_details')) {
            $showVideoDetails = in_array($request->show_video_details, [1, '1', 'true', true], true);
        }

        // ✅ Handle main image upload
        $mainImageName = null;
        if ($request->hasFile('main_image')) {
            $mainImage = $request->file('main_image');
            $mainImageName = time() . '_main_' . Str::random(10) . '.' . $mainImage->getClientOriginalExtension();
            Storage::disk('public')->put('events/' . $mainImageName, file_get_contents($mainImage->getRealPath()));
        }

        // ✅ Handle multiple event images upload
        $eventImagesNames = [];
        if ($request->hasFile('event_images')) {
            foreach ((array)$request->file('event_images') as $image) {
                if ($image && $image->isValid()) {
                    $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                    Storage::disk('public')->put('events/' . $imageName, file_get_contents($image->getRealPath()));
                    $eventImagesNames[] = $imageName;
                }
            }
        }

        // ✅ Create event
        $event = Event::create([
            'title' => $request->title,
            'description' => $request->description,
            'venue' => $request->venue,
            'status' => $request->status ?? 'Active',
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'show_video_details' => $showVideoDetails,
            'event_location' => $request->event_location,
            'main_image' => $mainImageName,
            'event_images' => $eventImagesNames,
            'user_id' => auth()->id(),
        ]);

        // ✅ Handle videos JSON
        if ($request->has('videos')) {
            $videos = json_decode($request->videos, true);
            if (is_array($videos)) {
                foreach ($videos as $video) {
                    EventVideo::create([
                        'event_id' => $event->event_id,
                        'title' => $video['title'] ?? '',
                        'url' => $video['url'] ?? '',
                    ]);
                }
            }
        }

        // ✅ Load relationships & add computed fields
        $event->load(['user:id,name,email', 'videos']);
        $event->main_image_url = $event->main_image_url;
        $event->event_images_urls = $event->event_images_urls;

        // ✅ Determine Event Type
        $now = Carbon::now();
        $event->EventType = Carbon::parse($event->start_time)->gte($now) ? 'newEvent' : 'oldEvent';

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{event_id}",
     *     summary="Get event by ID (Public - No authentication required)",
     *     tags={"Events"},
     *     @OA\Parameter(
     *         name="event_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Event details"),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function show($event_id)
    {
        $event = Event::with(['user:id,name,email', 'videos'])->find($event_id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $event->main_image_url = $event->main_image_url;
        $event->event_images_urls = $event->event_images_urls;
        
        // Add EventType attribute
        $now = Carbon::now();
        $event->EventType = Carbon::parse($event->start_time)->gte($now) ? 'newEvent' : 'oldEvent';

        return response()->json([
            'success' => true,
            'data' => $event
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{event_id}",
     *     summary="Update an event (Admin only)",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="event_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Annual Tech Conference"),
     *                 @OA\Property(property="description", type="string", example="An event for tech enthusiasts."),
     *                 @OA\Property(property="venue", type="string", example="Dubai World Trade Center"),
     *                 @OA\Property(property="status", type="string", enum={"Active", "Inactive", "Cancelled", "Completed"}),
     *                 @OA\Property(property="start_time", type="string", format="date-time", example="2025-10-20T10:00:00"),
     *                 @OA\Property(property="end_time", type="string", format="date-time", example="2025-10-20T18:00:00"),
     *                 @OA\Property(property="show_video_details", type="boolean", example=true),
     *                 @OA\Property(property="event_location", type="string", example="Hall 3, Gate A"),
     *                 @OA\Property(property="main_image", type="string", format="binary", description="Main event image"),
     *                 @OA\Property(
     *                     property="event_images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Multiple event images"
     *                 ),
     *                 @OA\Property(
     *                     property="videos",
     *                     type="string",
     *                     description="JSON string of videos. Example: [{title: Intro, url: https://youtu.be/abc}]"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Event updated successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=404, description="Event not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $event_id)
    {
        // ✅ Find event
        $event = Event::find($event_id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:Active,Inactive,Cancelled,Completed',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'show_video_details' => 'nullable|in:0,1,true,false',
            'event_location' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'event_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'videos' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // ✅ Handle main image upload
        if ($request->hasFile('main_image')) {
            // Delete old main image
            if ($event->main_image) {
                Storage::disk('public')->delete('events/' . $event->main_image);
            }
            
            $mainImage = $request->file('main_image');
            $mainImageName = time() . '_main_' . Str::random(10) . '.' . $mainImage->getClientOriginalExtension();
            Storage::disk('public')->put('events/' . $mainImageName, file_get_contents($mainImage->getRealPath()));
            $event->main_image = $mainImageName;
        }

        // ✅ Handle multiple event images upload
        if ($request->hasFile('event_images')) {
            // Delete old event images
            if ($event->event_images) {
                foreach ($event->event_images as $oldImage) {
                    Storage::disk('public')->delete('events/' . $oldImage);
                }
            }
            
            $eventImagesNames = [];
            foreach ((array)$request->file('event_images') as $image) {
                if ($image && $image->isValid()) {
                    $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                    Storage::disk('public')->put('events/' . $imageName, file_get_contents($image->getRealPath()));
                    $eventImagesNames[] = $imageName;
                }
            }
            $event->event_images = $eventImagesNames;
        }

        // ✅ Update event fields
        $updateData = $request->except(['main_image', 'event_images', 'videos']);
        
        // Convert show_video_details to boolean if present
        if ($request->has('show_video_details')) {
            $updateData['show_video_details'] = in_array($request->show_video_details, [1, '1', 'true', true], true);
        }

        $event->fill($updateData);
        $event->save();

        // ✅ Handle videos JSON update
        if ($request->has('videos')) {
            // Delete old videos
            EventVideo::where('event_id', $event->event_id)->delete();
            
            $videos = json_decode($request->videos, true);
            if (is_array($videos)) {
                foreach ($videos as $video) {
                    EventVideo::create([
                        'event_id' => $event->event_id,
                        'title' => $video['title'] ?? '',
                        'url' => $video['url'] ?? '',
                    ]);
                }
            }
        }

        // ✅ Load relationships & add computed fields
        $event->load(['user:id,name,email', 'videos']);
        $event->main_image_url = $event->main_image_url;
        $event->event_images_urls = $event->event_images_urls;
        
        // ✅ Determine Event Type
        $now = Carbon::now();
        $event->EventType = Carbon::parse($event->start_time)->gte($now) ? 'newEvent' : 'oldEvent';

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{event_id}",
     *     summary="Delete an event (Admin only)",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="event_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Event deleted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required"),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function destroy($event_id)
    {
        $event = Event::find($event_id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        // Delete main image
        if ($event->main_image) {
            Storage::disk('public')->delete('events/' . $event->main_image);
        }

        // Delete event images
        if ($event->event_images) {
            foreach ($event->event_images as $image) {
                Storage::disk('public')->delete('events/' . $image);
            }
        }

        // Delete videos
        EventVideo::where('event_id', $event->event_id)->delete();

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
}