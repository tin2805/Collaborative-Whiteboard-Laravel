<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\CanvasObjectCreated;
use App\Events\CanvasObjectUpdated;
use App\Events\CanvasCleared;
use App\Events\CanvasObjectsDeleted;
use App\Events\CanvasObjectDeleted;
use App\Events\ClearAllRequested;

use App\Models\Room;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('Rooms/Index', [
            'ownedRoomsCount' => $user->ownedRooms()->count(),
            'joinedRoomsCount' => $user->rooms()->count(),
            'publicRoomsCount' => Room::where('is_public', true)
                ->where('owner_id', '!=', $user->id)
                ->count(),
            'activeTab' => 'dashboard'
        ]);
    }


    public function myRooms()
    {
        $user = Auth::user();
        return Inertia::render('Rooms/Index', [
            'rooms' => $user->rooms()->where('owner_id', '!=', $user->id)->with('owner')->get(),
            'ownedRooms' => $user->ownedRooms()->with('owner')->get(),
            'activeTab' => 'my-rooms'
        ]);
    }

    public function publicRooms()
    {
        $user = Auth::user();
        return Inertia::render('Rooms/Index', [
            'publicRooms' => Room::where('owner_id', '!=', $user->id)
                ->whereDoesntHave('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with('owner')
                ->get(),
            'activeTab' => 'public-rooms'
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_public' => 'boolean',
            'password' => 'required_if:is_public,false|nullable|string'
        ]);

        $room = Auth::user()->ownedRooms()->create([
            'name' => $validated['name'],
            'is_public' => $request->boolean('is_public', true),
            'password' => $request->boolean('is_public', true) ? null : ($validated['password'] ?? null),
        ]);

        // Auto join the owner to the room
        $room->users()->attach(Auth::id(), ['role' => 'admin']);

        return redirect()->route('rooms.show', $room->id);
    }

    public function checkPrivate(string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['error' => 'Phòng không tồn tại'], 404);
        }

        $userInRoom = $room->users()->where('user_id', Auth::id())->exists();

        return response()->json([
            'is_public' => $room->is_public,
            'requires_password' => !$room->is_public && !empty($room->password) && !$userInRoom,
            'already_joined' => $userInRoom,
        ]);
    }

    public function join(Request $request, string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return redirect()->route('rooms.index')->with('error', 'Không tìm thấy phòng vẽ với mã ID này.');
        }

        if ($room->is_public || $room->users()->where('user_id', Auth::id())->exists()) {
             // Already in or public
             if (!$room->users()->where('user_id', Auth::id())->exists()) {
                 $room->users()->attach(Auth::id(), ['role' => 'editor']);
             }
             return redirect()->route('rooms.show', $room->id);
        }

        // Private
        $request->validate([
            'password' => 'nullable|string',
        ]);

        if (!empty($room->password)) {
            if (!\Illuminate\Support\Facades\Hash::check($request->password, $room->password)) {
                return redirect()->route('rooms.index')->with('error', 'Mật khẩu phòng không chính xác.');
            }
        }

        $room->users()->attach(Auth::id(), ['role' => 'editor']);

        return redirect()->route('rooms.show', $room->id);
    }

    public function show(string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return redirect()->route('rooms.index')->with('error', 'Không tìm thấy phòng vẽ với mã ID này.');
        }

        // Check permission
        if (!$room->is_public && !$room->users()->where('user_id', Auth::id())->exists()) {
            return redirect()->route('rooms.index')->with('error', 'Bạn không có quyền truy cập phòng này. Nếu có ID, hãy sử dụng tính năng "Tham gia bằng ID" ở trang chủ để nhập mật khẩu.');
        }

        // Ensure user is in the room users list if they are visiting
        if (!$room->users()->where('user_id', Auth::id())->exists()) {
            $room->users()->attach(Auth::id(), ['role' => 'editor']);
        }

        return Inertia::render('Rooms/Show', [
            'room' => $room->load('owner', 'users'),
            'canvasObjects' => $room->canvasObjects()->orderBy('z_index')->get(),
        ]);
    }

    public function kickMember(Room $room, User $user)
    {
        if ($room->owner_id !== Auth::id()) {
            return back()->with('error', 'Chỉ chủ phòng mới có quyền thao tác.');
        }

        if ($room->owner_id === $user->id) {
            return back()->with('error', 'Không thể kích chủ phòng.');
        }

        $room->users()->detach($user->id);

        broadcast(new \App\Events\UserRemovedFromRoom($room->id, $user->id, 'Chủ phòng đã mời bạn ra khỏi phòng vẽ.'));

        return back()->with('success', "Đã đuổi thành viên khỏi phòng.");
    }

    public function leaveRoom(Room $room)
    {
        $userId = Auth::id();

        if ($room->owner_id === $userId) {
            return back()->with('error', 'Là chủ phòng, bạn không thể rời phòng. Hãy sử dụng chức năng Dọn dẹp bảng hoặc Xóa phòng.');
        }

        $room->users()->detach($userId);

        return redirect()->route('rooms.index')->with('success', 'Bạn đã rời khỏi phòng vẽ.');
    }

    public function storeObject(Request $request, Room $room)
    {
        $validated = $request->validate([
            'id' => 'required|uuid',
            'type' => 'required|string',
            'data' => 'required|array',
            'z_index' => 'required|integer',
        ]);

        $object = $room->canvasObjects()->create([
            'id' => $validated['id'],
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'data' => $validated['data'],
            'z_index' => $validated['z_index'],
        ]);

        // Broadcast to others in the room
        broadcast(new CanvasObjectCreated($object))->toOthers();

        return response()->json($object);
    }

    public function updateObject(Request $request, Room $room, $objectId)
    {
        $object = $room->canvasObjects()->findOrFail($objectId);
        
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $object->update([
            'data' => $validated['data'],
        ]);

        broadcast(new CanvasObjectUpdated($object))->toOthers();

        return response()->json($object);
    }

    public function clearMyObjects(Room $room)
    {
        $room->canvasObjects()->where('user_id', Auth::id())->delete();
        
        broadcast(new CanvasObjectsDeleted($room, Auth::id()))->toOthers();

        return response()->json(['success' => true]);
    }

    public function update(Request $request, Room $room)
    {
        if ($room->owner_id != Auth::id()) {
            return back()->with('error', 'Bạn không có quyền thay đổi cài đặt phòng này.');
        }

        // Chuyển đổi định dạng is_public sang boolean chuẩn Laravel
        if ($request->has('is_public')) {
            $request->merge([
                'is_public' => $request->boolean('is_public')
            ]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_public' => 'sometimes|boolean',
            'password' => 'nullable|string',
        ]);

        if (array_key_exists('is_public', $validated) && $validated['is_public']) {
            $validated['password'] = null; // Clear password if made public
        } elseif (array_key_exists('password', $validated) && empty($validated['password'])) {
            unset($validated['password']); // Keep existing password if empty
        }

        $room->update($validated);

        return back()->with('success', 'Đã cập nhật cài đặt phòng vẽ.');
    }

    public function destroy(Room $room)
    {
        if ($room->owner_id !== Auth::id()) {
            return back()->with('error', 'Chỉ chủ phòng mới có quyền xóa phòng.');
        }

        // Xóa các đối tượng vẽ liên quan (Laravel cascade hoặc code)
        $room->canvasObjects()->delete();
        $room->users()->detach();
        $room->delete();

        return redirect()->route('rooms.index')->with('success', 'Đã xóa phòng vẽ thành công.');
    }

    public function clearAllObjects(Room $room)
    {
        if ($room->owner_id !== Auth::id()) {
            return response()->json(['error' => 'Chỉ chủ phòng mới có thể xóa toàn bộ bảng.'], 403);
        }

        $room->canvasObjects()->delete();
        
        broadcast(new CanvasCleared($room))->toOthers();

        return response()->json(['success' => true]);
    }

    public function requestClearAll(Request $request, Room $room)
    {
        // Broadcast a request to all users, but only the owner should ideally respond
        broadcast(new ClearAllRequested($room, Auth::user()))->toOthers();

        return response()->json(['success' => true]);
    }

    public function destroyObject(Request $request, Room $room, $objectId)
    {
        $object = $room->canvasObjects()->where('id', $objectId)->where('user_id', Auth::id())->firstOrFail();
        $object->delete();

        broadcast(new CanvasObjectDeleted($room, $objectId))->toOthers();

        return response()->json(['success' => true]);
    }
}
