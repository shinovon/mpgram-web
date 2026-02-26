# MPGram API documentation

> [!WARNING]
> API may change any time without notice.
>
> **MPGram API must not be used for bots, its only purpose is to make custom clients for legacy devices.**
>
> **Instance owners are advised to block any suspicious-looking User-Agents and IP addresses from data-centers.**

Usage example: `https://MPGRAM_INSTANCE/api.php?v=10&method=getPeer&id=nnmidlets`

- Current version: 10
- Minimum compatible version: 2

## Methods rules
- All methods requre authorization, unless stated otherwise.
- All methods return JSON objects, unless stated otherwise.
- All methods accept parameters with GET query, POST multipart or POST URL-encoded form.
- Any method may return [Error object](#Error) in case of failure.
- For authorization, set `X-mpgram-user` request header or `user` parameter via GET or POST.

## Client request headers

- `X-mpgram-user`: Authorization
- `X-mpgram-instance-password`: Instance password
- `X-mpgram-app-version`: Client app version
- `X-mpgram-device`: Device information
- `X-mpgram-system`: System information

## Server response headers

- `X-Server-Time`: Server time
- `X-Server-Api-Version`: Maximum supported API version
- `X-file-rewrite-supported`: `1` If server supports `file/FILENAME?c=PEER_ID&m=MESSAGE_ID` links
- `X-voice-conversion-supported`: `1` If server supports voice messages conversion

## Authorization flow example

### Phone number

1. Request: `method=phoneLogin&phone=+123`
<br>Response: `{"res":"need_captcha","captcha_id":"zxc"}`

2. Request: `method=getCaptchaImg&captcha_id=zxc`
<br>Response: JPEG Image

3. Request: `method=phoneLogin&phone=+123&captcha_id=zxc&captcha_key=54321`
<br>Response: `{"res":"code_sent","user":"asdfgh","phone_code_hash":"dsa"}`

4. Request: `method=completePhoneLogin&code=12345`
<br>Header: `X-mpgram-user: asdfgh`
<br>Response: `{"res":1}`

### QR code

1. Request: `method=initLogin&qr=1`
   <br>Response: `{"res":"need_captcha","captcha_id":"zxc"}`

2. Request: `method=getCaptchaImg&captcha_id=zxc`
   <br>Response: JPEG Image

3. Request: `method=initLogin&qr=1&captcha_id=zxc&captcha_key=54321`
   <br>Response: `{"res":"qr","user":"asdfgh","text":"dGc6Ly9zb21ldGhpbmc="}`

4. Request: `method=qrLogin`
   <br>Header: `X-mpgram-user: asdfgh`
   <br>Response: `{"res":1}`

# Models

## Message

Object

- `id` (int): Positive integer
- `date` (int): Time when message was sent
- `text` (string, optional): Text of message
- `out` (boolean, optional): true if outbox message
- `peer_id` (string, optional): [Peer ID](#Peer-ID) where message was sent
- `from_id` (string, optional): [Peer ID](#Peer-ID) of sender
- `fwd` (object, optional): Forward information, present if message is forwarded
  - `from_id` (string, optional): [Peer ID](#Peer-ID) of original sender
  - `date` (int, optional): Time when original message was sent
  - `msg` (int, optional): Original message ID, present if message is saved
  - `peer` (string, optional): [Peer ID](#Peer-ID) where original message was send, present if message is saved
  - `s` (boolean, optional): true if message is saved
  - `from_name` (string, optional): Name of original sender, present if profile is private
- `media` (object or null, optional): Media information, present if message contains media. <br>(~~null if media is disabled~~, **changed since v9**)
  - General properties:
    - `type` (string): Type of media
    - `hide` (int, optional): 1 if media is disabled. **since v9**
  - Photo:
    - `type`: `photo`
    - `id` (string): ID of photo
    - `date` (int or null): Date of photo
    - `w` (int): Width of photo. **since v9**
    - `h` (int): Height of photo. **since v9**
  - Document:
    - `type`: `document`
    - `id` (string)
    - `date` (int, optional)
    - `size` (int or null)
    - `mime` (string or null)
    - `thumb` (boolean): Has thumbnail?
    - `name` (string, optional): Original file name
    - `audio` (boolean, optional): true if media is audio
    - `voice` (boolean, optional): true if media is voice message
    - `wave` (boolean, optional): true if voice message has waveform
    - `time` (int, optional): Duration of audio in seconds
    - `title` (string, optional): Title meta tag of audio
    - `artist` (string, optional): Artist meta tag of audio
    - `w` (int, optional): Width of image, present if file is image. **since v10**
    - `h` (int, optional): Height of image, present if file is image. **since v10**
  - Webpage link
    - `type`: `webpage`
    - `url` (string or null): URL
    - `title` (string or null): Title
    - `name` (string or null): Description
  - Location
    - `type`: `geo`
    - `lat` (string or null): Latitude
    - `long` (string or null): Longitude
  - Poll (Unfinished)
    - `type`: `poll`
    - `voted` (int): Count of voters
  - Undefined
    - `type`: `undefined`
    - `_` (string): Raw MadelineProto type of media
- `act` (object, optional): Chat action information, present if message is action.
  - `_` (string): Type of action
  - `user` (string, optional): [Peer ID](#Peer-ID) of involved user
  - `t` (string, optional): New title of chat, present if action is renaming.
- `reply` (object, optional): Reply information, present if message is reply to other or is sent in forum chat
  - `id` (int or null): Replied message ID
  - `peer` (string, optional): [Peer ID](#Peer-ID) of replied message, present if message replied to other dialog
  - `quote` (string, optional): Quote part of replied message
  - `msg` (object, optional): [Message](#Message) object of replied message
  - `top` (int, optional): Top message ID, present if sent in forum chat. **since v10**
- `markup` (array, optional): Reply markup, array of button row arrays. **since v5**
  - Row (array):
    - Button (object):
      - `text` (string): Text
      - `data` (string, optional): Data to be sent with `sendBotCallback`, present if button is callback.
      - `url` (string, optional): URL, present if button is hyperlink
- `entities` (array, optional): Array of raw MadelineProto [MessageEntity](https://docs.madelineproto.xyz/API_docs/types/MessageEntity.html) objects. **since v5**
- `group` (int, optional): Message group ID, present if media message is grouped. **since v5**
- `edit` (int, optional): Date of edition, present if message was edited. **since v7**
- `silent` (boolean, optional): true if message sent without notification. **since v8**
- `mention` (boolean, optional): true if user is mentioned in message. **since v8**
- `comments` (object, optional): Post comments information, present if message is broadcast post that has comments. **since v8**
  - `count` (int): Count messages. 
  - `peer` (string): [Peer ID](#Peer-ID) of discussion chat.
  - `read` (int, optional): Message ID of max read message, present if discussion thread was read once.
- `reacts` (object, optional): Reacts information. **since v10** (Unfinished)
  - `count` (int): Count of reacts

### Removed since v5
- `reply`
  - `msg` (int or null): **replaced by `id`**



## Dialog

Object

- `id` (string): [Peer ID](#Peer-ID)
- `pin` (boolean, optional): true if dialog is pinned
- `unread` (int, optional): Count of unread messages, present if more than 0
- `mentions` (int, optional): Count of unread mentions, present if more than 0

### Removed since v5

- `pinned` (boolean, optional): true if dialog is pinned, **replaced by `pin`**
- `unread_count` (int): Count of unread mentions or 0, **replaced by `unread`**

### Removed since v2

- `type` (string): Type of peer, possible values: "user", "chat", or "channel".



## Peer ID

String

- Positive integer: User
- 0 to -1000000000000 exclusive: Chat
- -1000000000000 to -2000000000000 exclusive: Channel
- from -2000000000000 to -Infinity exclusive: Secret chat **(Unsupported!)**



## User

Object

- `id` (string): [Peer ID](#Peer-ID), positive integer
- `fn` (string or null): First name
- `ln` (string or null): Last name
- `name` (string, optional): Public link
- `p` (boolean, optional): true if user has photo. **since v5**
- `k` (boolean, optional): true if user is listed in contacts. **since v5**
- `s` (boolean, optional): true if user is online. **since v5**
- `w` (int, optional): Date when user was last online. **since v5**
- `a` (boolean, optional) true if user has admin rights, present only in `getParticipants` response. **since v6**

### Removed since v5
- `first_name` (string): First name, replaced by `fn`
- `last_name` (string): Last name, replaced by `ln`
- `username` (string or null): Public link, **replaced by `name`**



## Chat

Object

- `id` (string): [Peer ID](#Peer-ID), negative integer
- `type` (string): Type of chat. **deprecated, will be deleted in v11**
- `t` (string): Title of chat
- `name` (string, optional): Public link
- `p` (boolean, optional): true if chat has photo. **since v5**
- `c` (boolean, optional): true if broadcast channel. **since v5**
- `l` (boolean, optional): true if logged user is not in this chat. **since v5**

### Removed since v5
- `title` (string): Title of chat, **replaced by `t`**
- `username` (string or null): Public link, **replaced by `name`**



## Messages history response

Object

- `count` (int, optional): Total count of messages
- `off` (int, optional): Offset
- `messages` (array, optional): Array of [Message](#Message) objects
- `users` (object, optional): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object, optional): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)



## Authorization response

Object

- `res` (string or int)
  - 1: Authorization completed, client should call `me`.
  - `code_sent`: Authorization code is sent, client must call `completePhoneLogin`.
  - `qr`: QR code is generated, `text` contains Base64 encoded authorization link, client must call `qrLogin` after QR code is scanned.
  - `phone_code_invalid`: Authorization code is invalid, code will be resent, client must call `completePhoneLogin` again with correct code.
  - `phone_code_expired`: Authorization code is expired, code will be resent, client must call `completePhoneLogin` again with correct code.
  - `need_captcha`: Captcha required, client must call `getCaptchaImg` with provided `captcha_id`, then client must call method again with `captcha_id` and `captcha_key`.
  - `password`: Cloud password requested, must call `complete2faLogin`.
  - `auth_restart`: Authorization failed, client must call `phoneLogin` again.
  - `phone_number_invalid`: Phone number is invalid, cannot proceed.
  - `no_password`: Cloud password is enabled but not set, cannot proceed.
  - `need_signup`: Phone number is unregistered, cannot proceed.
  - `exception`: Server side error occurred.
- `user` (string, optional): User code, if present, client must save it and use it in all subsequent requests as `X-mpgram-user` header.
- `phone_code_hash` (string, optional): Phone code hash to be used in `resendCode`, present in `phoneLogin` response or if `completePhoneLogin` was unsuccessful
- `message` (string, optional): Error message



## Error

Object

- `error` (object)
  - `message` (string): Error message
  - `stack_trace` (string, optional): Exception stack trace

Most common error messages:
- `API is disabled`: Server does not support API requests
- `Instance password is required`: Server requires password, client must provide valid `X-mpgram-instance-password` header
- `Unsupported API version`: Client API version is outdated
- `Login API is disabled`: Server does not accept authorization through API
- `Invalid authorization`: Requested method requires valid authorization
- `Could not get user info`, `Failed to load session`: Authorization session expired



# Authorization methods

## `phoneLogin`

### Description
Initializes user authorization by phone number.
<br>
If response has `captcha_id`, request must be repeated with `captcha_id` and `captcha_key` set.

Does not require authorization

### Parameters
- `phone`: Phone number
- `captcha_id` (optional): Captcha ID
- `captcha_key` (optional): Captcha text

#### Response
[Authorization response object](#Authorization-response)



## `initLogin`

### Description
Initializes user authorization by QR code.
<br>
If response has `captcha_id`, request must be repeated with `captcha_id` and `captcha_key` set.

Does not require authorization

### Parameters
- `qr`: must be set to `1`
- `captcha_id` (optional): Captcha ID
- `captcha_key` (optional): Captcha text



### Response
[Authorization response object](#Authorization-response)



## `qrLogin`

### Description
Checks QR code authorization status, generates new link if authorization is not complete.

### Response
[Authorization response object](#Authorization-response)



## `completePhoneLogin`

### Description
Checks authorization code

### Parameters
- `code`

### Response
[Authorization response object](#Authorization-response)



## `complete2faLogin`

### Description
Checks cloud password

### Parameters
- `password`: Cloud password

#### Response
[Authorization response object](#Authorization-response)



## `resendCode`

### Description
Resends authorization code

### Parameters
- `phone`: Phone number
- `hash`: `phone_code_hash` provided in previous [authorization response](#Authorization-response)

### Response
Object

- `res`: 1 if request completed successfully



## `getCaptchaImg`
### Description
Returns captcha image

Does not require authorization

### Parameters
- `captcha_id`: Captcha ID generated by authorization method.

### Response
JPEG image



## `checkAuth`
**Deprecated since v5, use [me](#me) method**

### Response
Object

- `res`: 1 if request completed successfully



## `completeSignup`
**Removed since v2**

### Parameters
- `first_name`: First name
- `last_name`: Last name

### Response
Object

- `res`: 1 if request completed successfully



## `logout`
**Removed since v2, you may use `login.php?logout=1` from web version**



# Peers methods

## `me`
(Alias `getSelf` **deprecated since v5**)

### Description
Returns information about of logged user.

### Parameters
`status` (optional): set to `1` to set online status. **since v5**

### Response
[User](#User) object



## `getPeer`

### Description
Returns information about peer.

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
[User](#User) or [Chat](#Chat) object



## `getPeers`

### Description
Returns information about list of peers.

### Parameters
- `id`: Comma-separated [Peer IDs](#Peer-ID)

### Response
Object

- `users` (object): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)



## `getFullInfo`

### Description
Returns detailed raw information about peer.

Passed directly to MadelineProto API.

See: https://docs.madelineproto.xyz/FullInfo.html

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
MadelineProto `FullInfo` object



## `getInfo`

### Description
Returns raw information about peer.

Passed directly to MadelineProto API.

See: https://docs.madelineproto.xyz/Info.html

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
MadelineProto `Info` object



## `resolvePhone`

### Description
Returns raw user information for provided phone number, if their privacy settings allow it.

Passed directly to MadelineProto API.

See: https://docs.madelineproto.xyz/API_docs/types/contacts.ResolvedPeer.html

### Parameters
- `phone`: Phone number

### Response
Object

- `res` (object): MadelineProto `contacts.ResolvedPeer` object



# Dialogs methods
## `getDialogs`

### Description
TODO

### Parameters
- `offset_id` (optional)
- `offset_date` (optional)
- `offset_limit` (optional)
- `offset_id` (optional)
- `f` (optional): Folder ID
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
- `dialogs` (array): Array of [Dialog](#Dialog) objects
- `messsage` (object, optional): Map of [Message](#Message) objects by their [Peer IDs](#Peer-ID)
- `users` (object, optional): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object, optional): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)



## `getAllDialogs`

### Description
Returns all dialogs of logged user.

### Parameters
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
- `dialogs` (array): Array of [Dialog](#Dialog) objects
- `messsage` (object, optional): Map of [Message](#Message) objects by their [Peer IDs](#Peer-ID)
- `users` (object, optional): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object, optional): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)



## `getContacts`

### Description
Returns list of contacts of logged user.

### Response
Object

- `res` (array): Array of [User](#User) objects 



## `getFolders`

### Description
Returns list of dialog folders of logged user.

### Response
Object

- `res` (array or null): Array of folders
  - Folder (object)
    - `id` (int): Folder ID
    - `t` (string, optional): Folder title
- `archive` (boolean. optional): true if user has archived chats



## `getDialog`

### Description
Returns specific dialog. TODO

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
Object

- `res` (object)



## `searchChats`

TODO



# Messages methods

## `getHistory`

### Description
Returns history of messages. TODO

### Parameters
- `peer`: Peer ID
- `offset_id` (optional)
- `offset_date` (optional)
- `add_offset` (optional)
- `limit` (optional)
- `max_id` (optional)
- `min_id` (optional)
- `read` (optional): Set to 1 to mark messages as read
- `media` (optional): Set to 1 to include media in messages
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
[Messages history response](#Messages-history-response)



## `getMessages`

### Description
Returns specific messages.

### Parameters
- `peer`: Peer ID
- `id`: Comma-separated list of message IDs
- `read` (optional): Set to 1 to mark messages as read
- `media` (optional): Set to 1 to include media in messages
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
[Messages history response](#Messages-history-response)



## `searchMessages`

### Description
Returns filtered history of messages. TODO

### Parameters
- `peer`: Peer ID
- `q` (optional): Search query
- `filter` (optional): One of values: `Photos`, `Video`, `Document`, `Music`, `Voice`
- `offset_id` (optional): Offset message ID
- `offset_date` (optional)
- `add_offset` (optional)
- `limit` (optional): Messages limit
- `max_id` (optional): Maximum message ID
- `min_id` (optional): Minimum message ID
- `top_msg_id` (optional): Thread top message ID
- `read` (optional): Set to 1 to mark messages as read
- `media` (optional): Set to 1 to include media in messages
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
[Messages history response](#Messages-history-response)







## `readMessages`

### Description
Marks messages as read.

### Parameters
- `peer`: Peer ID
- `max`: Max message ID
- `thread` (optional): Thread top message ID, used for forum chats

### Response
Nothing



## `deleteMessage`

### Description
Deletes message.

### Parameters
- `peer`: Peer ID
- `id`: Comma-separated list of message IDs. **since v9**

#### Changed since v9
- `id`: Message ID.

### Response
Object

- `res`: 1 if request completed successfully



## `sendMessage`

TODO



## `sendMedia`

TODO



## `editMessage`

TODO



## `setTyping`

### Description
Updates typing status.

### Parameters
- `peer`: Peer ID
- `action`: One of values: `Typing`, `Cancel`

### Response
Object

- `res`: 1 if request completed successfully



## `getForumTopics`

### Description
Returns list of topics in forum chat. TODO

### Parameters
- `peer`: Peer ID
- `limit`: Response limit

### Reponse
Object

- `res` (array)
  - Topic (object)
    - `id` (int)
    - `closed` (boolean)
    - `pinned` (boolean)
    - `date` (int)
    - `top` (int): Thread top message ID
    - `unread` (int)
    - `read_max_id` (int)
    - `title` (string, optional)



## `sendVote`

TODO



## `pinMessage`

### Description
Pins message.

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `id`: Message ID
- `unpin` (optional): set 1 to unpin previous pinned message
- `silent` (optional): set to 0 to send notification or 1 to not, 1 by default

### Response
Object

- `res`: 1 if request completed successfully



## `getDiscussionMessage`

### Description
Returns information about post comments. TODO

### Parameters
`peer`: Peer ID
`msg_id`: Message ID

### Response
Object

- `id` (int): Message ID
- `peer_id` (string): [Peer ID](#Peer-ID)
- `unread` (int)
- `ead` (int)
- `max_id` (int)



## `startBot`

### Description
TODO

### Parameters
- `id`: Peer ID
- `start` (optional): Start parameter
<!-- - `random` (optional): Random seed -->

### Response
Object

- `res`: 1 if request completed successfully



## `sendBotCallback`
(Alias `botCallback` **deprecated since v10**)

### Description
Sends bot callback

See: https://docs.madelineproto.xyz/API_docs/types/messages.BotCallbackAnswer.html

### Parameters
- `peer`: Peer ID
- `msg_id`: Message ID
- `data`: Base64 encoded data
- `timeout` (optional): Answer timeout in seconds, decimal

### Response
MadelineProto messages.BotCallbackAnswer object




## `getStickerSets`

### Description
Returns sticker sets of logged user. TODO

### Response
Object

- `res` (array)
  - Sticker set (object)
    - `id` (string)
    - `access_hash` (string)
    - `title` (string)
    - `short_name` (string, optional)



## `getStickerSet`

### Description
Returns specific sticker set.

### Parameters
- `id`: Sticker set ID
- `access_hash`: Sticker set access hash
- `slug`: Sticker set short name

### Response
Object
- `id` (string): Sticker set ID
- `access_hash`: Sticker set access hash
- `title` (string, optional): Title
- `short_name` (string, optional)
- `installed` (int, optional): Date when sticker set was installed
- `count` (int, optional): Count of stickers
- `res` (array): Array of stickers
  - Sticker (object)
    - `id` (string): Document ID
    - `access_hash` (string): Document access hash
    - `mime` (string): MIME type



## `installStickerSet`

### Description
Adds sticker set.

### Parameters
- `id`: Sticker set ID
- `access_hash`: Sticker set access hash
- `slug`: Sticker set short name

### Response
Object

- `res`: 1 if request completed successfully



## `checkChatInvite`

TODO



## `importChatInvite`

TODO



# Channels methods

## `joinChannel`

TODO



## `leaveChannel`

TODO



## `getParticipants`

TODO



## `banMember`

TODO



# Updates methods

## `updates`
TODO

## `getLastUpdate`
TODO

## `cancelUpdates`
TODO

## `updateStatus`
TODO



# Notifications methods

## `notifications`
TODO

## `getNotifySettings`

### Description
Returns notification settings of logged user. TODO

### Response
Object
- `users` (int)
- `chats` (int)
- `broadcasts` (int)


# Misc methods

## `getServerTimeOffset`

### Description
Returns server timezone.

Does not require authorization.

### Response
Object
- `res` (int): Timezone offset in seconds 



# Additional endpoints

## `file.php`

TODO



## `ava.php`

TODO



## `voice.php`

TODO



# Methods availability by API versions
v1:
- `getCaptchaImg`
- `initLogin`
- `completePhoneLogin`
- `complete2faLogin`
- `completeSignup` **Removed since v2**
- `logout` **Removed since v2**
- `getServerTimeOffset`
- `getDialogs`
- `getAllDialogs`
- `getHistory`

v2:
- `phoneLogin`
- `sendMessage`
- `checkAuth` **Deprecated since v5, replaced by `me`**
- `getSelf` **Deprecated since v5, replaced by `me`**
- `getUser` **Removed since v3, replaced by `getPeer`**
- `getChat` **Removed since v3, replaced by `getPeer`**

v3:
- `getPeer`

v4:
- `getPeers`

v5: 
- `me`
- `updates`
- `getFullInfo`
- `getFolders`
- `readMessages`
- `startBot`
- `getContacts`
- `joinChannel`
- `leaveChannel`
- `checkChatInvite`
- `importChatInvite`
- `editMessage`
- `deleteMessage`
- `resolvePhone`
- `resendCode`

v6:
- `searchMessages`
- `getMessages`
- `getParticipants`
- `getLastUpdate`
- `setTyping`
- `updateStatus`
- `sendMedia`
- `searchChats`
- `editMessage`
- `banMember`
- `getForumTopics`
- `botCallback` **Deprecated since v10, replaced by `sendBotCallback`**
- `sendVote`
- `getStickerSets`
- `getStickerSet`

v7:
- `pinMessage`
- `installStickerSet`

v8:
- `getNotifySettings`
- `notifications`
- `getDiscussionMessage`
- `getInfo`

v9:
- `cancelUpdates`
- `getDialog`

v10:
- `sendBotCallback`
- `qrLogin`
