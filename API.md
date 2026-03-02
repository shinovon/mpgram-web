# MPGram API documentation

> [!WARNING]
> API may change any time without notice.
>
> **MPGram API must not be used for bots, its only purpose is to make custom clients for legacy devices.**
>
> **Instance owners are advised to block any suspicious-looking User-Agents and IP addresses from data-centers.**

Usage example: `https://MPGRAM_INSTANCE/api.php?v=10&method=getPeer&id=nnmidlets`

- Current version: 11
- Minimum compatible version: 2

## Methods rules
- All methods require authorization, unless stated otherwise.
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
- `X-mpgram-unicode`: Set to 1 to mark that client supports UTF-8 decoding properly, otherwise Unicode characters will be escaped in responses.
- `X-mpgram-keep-emoji`: Set to 1 to keep emoji symbols in names. **since v7**

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
- `date` (long): Time when message was sent
- `text` (string, optional): Text of message
- `out` (boolean, optional): true if outbox message
- `peer_id` (string, optional): [Peer ID](#Peer-ID) where message was sent
- `from_id` (string, optional): [Peer ID](#Peer-ID) of sender
- `fwd` (object, optional): Forward information, present if message is forwarded
  - `from_id` (string, optional): [Peer ID](#Peer-ID) of original sender
  - `date` (long, optional): Time when original message was sent
  - `msg` (int, optional): Original message ID, present if message is saved
  - `peer` (string, optional): [Peer ID](#Peer-ID) where original message was send, present if message is saved
  - `s` (boolean, optional): true if message is saved
  - `from_name` (string, optional): Name of original sender, present if profile is private
- `media` (object, optional): Media information, present if message contains media.
  - General properties:
    - `type` (string): Type of media
    - `hide` (int, optional): 1 if media is disabled. **since v9**
  - Photo:
    - `type`: `photo`
    - `id` (string): ID of photo
    - `date` (long or null): Date of photo
    - `w` (int): Width of photo. **since v9**
    - `h` (int): Height of photo. **since v9**
  - Document:
    - `type`: `document`
    - `id` (string)
    - `date` (long, optional)
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
  - Poll. **since v11**
    - `type`: `poll`
    - `text` (string): Question
    - `id` (string): Poll ID
    - `voted` (int, optional): Count of voters
    - `closed` (boolean): true if poll is closed
    - `public` (boolean, optional): true if poll is not anonymous
    - `multi` (boolean, optional): true if poll has multiple options
    - `quiz` (boolean, optional): true if poll has correct answer
    - `options` (array)
      - Option (object)
        - `text` (string): Text
        - `data` (string): Data for [sendVote](#sendVote)
        - `chosen` (boolean, optional): true if option is chosen by logged user
        - `correct` (boolean, optional): true if option is correct, present if poll is quiz.
        - `voters` (int, optional): Count of votes for this option
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
- `group` (long, optional): Message group ID, present if media message is grouped. **since v5**
- `edit` (long, optional): Date of edition, present if message was edited. **since v7**
- `silent` (boolean, optional): true if message sent without notification. **since v8**
- `mention` (boolean, optional): true if user is mentioned in message. **since v8**
- `comments` (object, optional): Post comments information, present if message is broadcast post that has comments. **since v8**
  - `count` (int): Count of messages. 
  - `peer` (string): [Peer ID](#Peer-ID) of discussion chat.
  - `read` (int, optional): Message ID of max read message, present if discussion thread was read once.
- `reacts` (object, optional): Reacts information. **since v10**
  - `count` (int): Count of reacts

<details>
<summary>Changes</summary>

### Changes since v9
- `media` can no longer be null

### Removed since v5
- `reply`
  - `msg` (int or null): **Repurposed, replaced by `id`**

</details>

---

## Dialog

Object

- `id` (string): [Peer ID](#Peer-ID)
- `pin` (boolean, optional): true if dialog is pinned
- `unread` (int, optional): Count of unread messages, present if more than 0
- `mentions` (int, optional): Count of unread mentions, present if more than 0
- `msg` (object, optional): Last message, present in `getDialogs` response. **since v5**


<details>
<summary>Changes</summary>

### Removed since v5

- `pinned` (boolean, optional): true if dialog is pinned, **replaced by `pin`**
- `unread_count` (int): Count of unread messages or 0, **replaced by `unread`**

### Removed since v2

- `type` (string): Type of peer, possible values: "user", "chat", or "channel".

</details>

---

## Peer ID

String

- Positive integer: User
- 0 to -1000000000000 exclusive: Chat
- -1000000000000 to -2000000000000 exclusive: Channel
- from -2000000000000 to -Infinity exclusive: Secret chat **(Unsupported!)**

---

## User

Object

- `id` (string): [Peer ID](#Peer-ID), positive integer
- `fn` (string or null): First name
- `ln` (string or null): Last name
- `name` (string, optional): Public link
- `p` (boolean, optional): true if user has photo. **since v5**
- `k` (boolean, optional): true if user is listed in contacts. **since v5**
- `s` (boolean, optional): true if user is online, present if `status` is listed in `fields` parameter. **since v5**
- `w` (long, optional): Date when user was last online or 0 if hidden, present if `status` is listed in `fields` parameter. **since v5**
- `a` (boolean, optional) true if user has admin rights, present only in `getParticipants` response. **since v6**

<details>
<summary>Changes</summary>

### Removed since v5
- `first_name` (string): First name, replaced by `fn`
- `last_name` (string): Last name, replaced by `ln`
- `username` (string or null): Public link, **replaced by `name`**

</details>

---

## Chat

Object

- `id` (string): [Peer ID](#Peer-ID), negative integer
- `type` (string): Type of chat. **deprecated, will be deleted in v11**
- `t` (string): Title of chat
- `name` (string, optional): Public link
- `p` (boolean, optional): true if chat has photo. **since v5**
- `c` (boolean, optional): true if broadcast channel. **since v5**
- `l` (boolean, optional): true if logged user is not in this chat. **since v5**

<details>
<summary>Changes</summary>

### Removed since v5
- `title` (string): Title of chat, **replaced by `t`**
- `username` (string or null): Public link, **replaced by `name`**

</details>

---

## Update

Object

- `update` (object): Almost raw MadelineProto `Update` object, see [https://docs.madelineproto.xyz/API_docs/types/Update.html](https://docs.madelineproto.xyz/API_docs/types/Update.html)
  - `_` (string): Type of update
  - `message` (object, optional): [Message](#Message) object
- `update_id` (int): Update ID

---

## Messages history response

Object

- `count` (int, optional): Total count of messages
- `off` (int, optional): History offset
- `messages` (array, optional): Array of [Message](#Message) objects
- `users` (object, optional): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object, optional): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)

---

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

---

## Error

Object

- `error` (object)
  - `message` (string): Error message
  - `stack_trace` (string, optional): Exception stack trace

Most common error messages:
- `API is disabled`: Server does not support API requests
- `Instance password is required`, `Wrong instance password`: Server requires password, client must provide valid `X-mpgram-instance-password` header
- `Unsupported API version`: Client API version is outdated
- `Login API is disabled`: Server does not accept authorization through API
- `Invalid authorization`: Requested method requires valid authorization
- `Could not get user info`, `Failed to load session`: Authorization session expired

---

# Authorization methods

## `phoneLogin`

### Description
Initializes user authorization by phone number.
<br>
If response has `captcha_id`, request must be repeated with `captcha_id` and `captcha_key` set.

Does not require authorization

Available since v2

### Parameters
- `phone`: Phone number
- `captcha_id` (optional): Captcha ID
- `captcha_key` (optional): Captcha text

#### Response
[Authorization response object](#Authorization-response)

---

## `initLogin`

### Description
Initializes user authorization by QR code.
<br>
If response has `captcha_id`, request must be repeated with `captcha_id` and `captcha_key` set.

Does not require authorization

Available since v10

### Parameters
- `qr`: must be set to `1`
- `captcha_id` (optional): Captcha ID
- `captcha_key` (optional): Captcha text

### Response
[Authorization response object](#Authorization-response)

---

## `qrLogin`

### Description
Checks QR code authorization status, generates new link if authorization is not complete.

Available since v10

### Response
[Authorization response object](#Authorization-response)

---

## `completePhoneLogin`

### Description
Checks authorization code

Available since v1

### Parameters
- `code`

### Response
[Authorization response object](#Authorization-response)

---

## `complete2faLogin`

### Description
Checks cloud password

Available since v1

### Parameters
- `password`: Cloud password

#### Response
[Authorization response object](#Authorization-response)

---

## `resendCode`

### Description
Resends authorization code

Available since v5

### Parameters
- `phone`: Phone number
- `hash`: `phone_code_hash` provided in previous [authorization response](#Authorization-response)

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `getCaptchaImg`
### Description
Returns captcha image

Does not require authorization

Available since v1

### Parameters
- `captcha_id`: Captcha ID generated by authorization method.

### Response
JPEG image

---

## `checkAuth`
**Deprecated since v5, use [me](#me) method**

Available since v2

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `completeSignup`
**Removed since v2**

### Parameters
- `first_name`: First name
- `last_name`: Last name

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `logout`

### Description
Destroys user authorization code

Available since v11

### Parameters
- `s`: Set to 1 to only delete session files

### Response
Object

- `res` (int): 1 if request completed successfully

---

# Peers methods

## `me`
(Alias `getSelf` available since v2, **deprecated since v5**)

### Description
Returns information about of logged user.

Available since v5

### Parameters
`status` (optional): set to `1` to set online status. **since v5**

### Response
[User](#User) object

---

## `getPeer`

### Description
Returns information about peer.

Available since v3

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
[User](#User) or [Chat](#Chat) object

---

## `getPeers`

### Description
Returns information about list of peers.

Available since v4

### Parameters
- `id`: Comma-separated [Peer IDs](#Peer-ID)

### Response
Object

- `users` (object): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)

---

## `getFullInfo`

### Description
Returns raw detailed information about peer.

See: https://docs.madelineproto.xyz/FullInfo.html

Available since v5

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
MadelineProto `FullInfo` object

---

## `getInfo`

### Description
Returns raw information about peer.

See: https://docs.madelineproto.xyz/Info.html

Available since v8

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
MadelineProto `Info` object

---

## `resolvePhone`

### Description
Returns raw user information for provided phone number, if their privacy settings allow it.

See: https://docs.madelineproto.xyz/API_docs/types/contacts.ResolvedPeer.html

Available since v5

### Parameters
- `phone`: Phone number

### Response
Object

- `res` (object): MadelineProto `contacts.ResolvedPeer` object

---

# Dialogs methods
## `getDialogs`

### Description
Returns list of dialogs of logged user.

Supports folder filters since v5.

Available since v1

### Parameters
- `offset_id` (optional): Used for pagination, does not work if `f` is set.
- `offset_date` (optional): Used for pagination, does not work if `f` is set.
- `offset_peer` (optional): Used for pagination, does not work if `f` is set.
- `limit` (optional): Limit of dialogs per response, 100 by default.
- `f` (optional): Folder ID, set to 1 for archived chats. **since v5**
- `fields` (optional): Comma-separated list of possible values: `dialogs`, `users`, `chats`, ~~`raw`~~, ~~`messages`~~

### Response
- `count` (int, optional): Count of found dialogs.
- `dialogs` (array): Array of [Dialog](#Dialog) objects
- `users` (object, optional): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object, optional): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)
- `raw` (object, optional): Raw result. **Deprecated**

<details>
<summary>Changes</summary>

#### Removed since v5
- `messages` (object, optional): Map of [Message](#Message) objects by their [Peer IDs](#Peer-ID).

</details>

---

## `getAllDialogs`

### Description
Returns all dialogs of logged user.

**Deprecated, do not use**

Available since v1

### Parameters
- `limit` (optional): Limit of dialogs, 100 by default.
- `fields` (optional): Comma-separated list of possible values: `dialogs`, `users`, `chats`, ~~`raw`~~, ~~`messages`~~

### Response
- `dialogs` (array): Array of [Dialog](#Dialog) objects
- `users` (object, optional): Map of [User](#User) objects by their [Peer IDs](#Peer-ID)
- `chats` (object, optional): Map of [Chat](#Chat) objects by their [Peer IDs](#Peer-ID)
- `raw` (object, optional): Raw result. **Deprecated**

<details>
<summary>Changes</summary>

#### Removed since v5
- `messages` (object, optional): Map of [Message](#Message) objects by their [Peer IDs](#Peer-ID). **since v5**

</details>

---

## `getContacts`

### Description
Returns list of contacts of logged user.

Available since v5

### Response
Object

- `res` (array): Array of [User](#User) objects 

---

## `getFolders`

### Description
Returns list of dialog folders of logged user.

Available since v5

### Response
Object

- `res` (array or null): Array of folders
  - Folder (object)
    - `id` (int): Folder ID
    - `t` (string, optional): Folder title
- `archive` (boolean. optional): true if user has archived chats

---

## `getDialog`

### Description
Returns raw information about dialog.

See: https://docs.madelineproto.xyz/API_docs/types/Dialog.html

Available since v9

### Parameters
- `id`: [Peer ID](#Peer-ID)

### Response
Object

- `res` (object): MadelineProto `Dialog` object

---

## `searchChats`

### Description
Search dialogs.

Available since v6

### Parameters
- `q`: Search query

### Response
Object

- `res` (array): Array of [User](#User) and [Chat](#Chat) objects

---

# Messages methods

## `getHistory`

### Description
Returns history of messages.

Available since v1

### Parameters
- `peer`: Peer ID
- `offset_id` (optional)
- `offset_date` (optional)
- `add_offset` (optional)
- `limit` (optional): Messages limit
- `max_id` (optional): Maximum message ID, exclusive
- `min_id` (optional): Minimum message ID, exclusive
- `read` (optional): Set to 1 to mark messages as read
- `media` (optional): Set to 1 to include media in messages
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
[Messages history response](#Messages-history-response)

---

## `getMessages`

### Description
Returns specific messages.

Available since v6

### Parameters
- `peer`: Peer ID
- `id`: Comma-separated list of message IDs
- `read` (optional): Set to 1 to mark messages as read
- `media` (optional): Set to 1 to include media in messages
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
[Messages history response](#Messages-history-response)

---

## `searchMessages`

### Description
Returns filtered history of messages.

Available since v6

### Parameters
- `peer`: Peer ID
- `q` (optional): Search query
- `filter` (optional): Possible values: `Photos`, `Video`, `Document`, `Music`, `Voice`
- `top_msg_id` (optional): Thread top message ID
- `offset_id` (optional): Offset message ID
- `offset_date` (optional)
- `add_offset` (optional)
- `limit` (optional): Messages limit
- `max_id` (optional): Maximum message ID, exclusive
- `min_id` (optional): Minimum message ID, exclusive
- `read` (optional): Set to 1 to mark messages as read
- `media` (optional): Set to 1 to include media in messages
- `fields` (optional): Comma-separated list of possible values: `messages`, `users`, `chats`

### Response
[Messages history response](#Messages-history-response)

---

## `readMessages`

### Description
Marks messages as read.

Available since v5

### Parameters
- `peer`: Peer ID
- `max`: Max message ID
- `thread` (optional): Thread top message ID, used for forum chats

### Response
Nothing

---

## `deleteMessage`

### Description
Deletes message.

Available since v5

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `id`: Comma-separated list of message IDs. **since v9**

#### Changed since v9
- `id`: Message ID.

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `sendMessage`

### Description
Sends message.

Available since v2

Combined with message forwarding method, set `fwd_from` and `id` to forward a message before sending.

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `text` (optional): Text of message
- `html` (optional): Set to 1 to enable HTML parsing
- `reply` (optional): Message ID to reply
- `fwd_from` (optional): Peer ID to forward message from
- `id` (optional): Message ID to forward, required if `fwd_from` is set

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `sendMedia`

### Description
Sends media message.

Combined with message forwarding method, set `fwd_from` and `id` to forward a message before sending.

Either `file`, or `doc_id` with `doc_access_hash` have to be set.

Available since v6

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `text` (optional): Text of message
- `html` (optional): Set to 1 to enable HTML parsing
- `reply` (optional): Message ID to reply
- `file` (optional): File via multipart request
- `uncompressed` (optional): Set to 1 to send media uncompressed
- `spoiler` (optional): Set to 1 to hide media under spoiler
- `doc_id` (optional): Document ID
- `doc_access_hash` (optional): Document access hash
- `fwd_from` (optional): Peer ID to forward message from
- `id` (optional): Message ID to forward, required if `fwd_from` is set

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `editMessage`

### Description
Edits sent message.

Available since v5

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `id`: Message ID
- `text` (optional): Text of message
- `html` (optional): Set to 1 to enable HTML parsing
- `reply` (optional): Message ID to reply
- `file` (optional): File
- `uncompressed` (optional): Set to 1 to send media uncompressed
- `spoiler` (optional): Set to 1 to hide media under spoiler
- `doc_id` (optional): Document ID
- `doc_access_hash`: Document access hash

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `setTyping`

### Description
Updates typing status.

Available since v6

### Parameters
- `peer`: Peer ID
- `action`: Possible values: `Typing`, `Cancel`

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `getForumTopics`

### Description
Returns list of topics in forum chat.

Available since v6

### Parameters
- `peer`: Peer ID
- `limit` (optional): Response limit, 30 by default.

### Response
Object

- `res` (array)
  - Topic (object)
    - `id` (int): Topic ID
    - `closed` (boolean): true if no messages can be sent in this topic
    - `pinned` (boolean): true if topic is pinned
    - `date` (long): Topic creating date
    - `top` (int): Last message ID in this topic
    - `unread` (int): Count of unread messages
    - `read_max_id` (int): Maximum ID of read messages
    - `title` (string, optional): Title of topic

---

## `pinMessage`

### Description
Pins message.

Available since v7

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `id`: Message ID
- `unpin` (optional): set 1 to unpin previous pinned message
- `silent` (optional): set to 0 to send notification or 1 to not, 1 by default

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `getDiscussionMessage`

### Description
Returns information about post comments.

Available since v8

### Parameters
- `peer`: Peer ID
- `msg_id`: Message ID

### Response
Object

- `id` (int): Thread top message ID
- `peer_id` (string): [Peer ID](#Peer-ID) of discussion chat
- `unread` (int): Count of unread messages
- `read` (int): Last read message ID
- `max_id` (int): Last message ID in discussion thread

---

## `startBot`

### Description

Starts conversation with a bot.

Available since v5

### Parameters
- `id`: Bot [Peer ID](#Peer-ID)
- `peer` (optional): [Peer ID](#Peer-ID). **since v11**
- `start` (optional): Start parameter

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `sendBotCallback`
(Alias `botCallback` available since v6, **deprecated since v10**)

Available since v10

### Description
Sends bot callback

See: https://docs.madelineproto.xyz/API_docs/types/messages.BotCallbackAnswer.html

### Parameters
- `peer`: Peer ID
- `msg_id`: Message ID
- `data`: Base64 encoded data
- `timeout` (optional): Answer timeout in seconds, positive decimal, 0.5 by default

### Response
MadelineProto messages.BotCallbackAnswer object

---

## `getStickerSets`

### Description
Returns saved sticker sets of logged user.

Available since v6

### Response
Object

- `res` (array)
  - Sticker set (object)
    - `id` (string): Sticker set ID
    - `access_hash` (string): Sticker set access hash
    - `title` (string): Title
    - `short_name` (string, optional): Sticker set short name

---

## `getStickerSet`

### Description
Returns sticker set information.

Either `id` with `access_hash`, or `slug` have to be set.

Available since v6

### Parameters
- `id` (optional): Sticker set ID
- `access_hash` (optional): Sticker set access hash
- `slug` (optional): Sticker set short name

### Response
Object
- `id` (string): Sticker set ID
- `access_hash`: Sticker set access hash
- `title` (string, optional): Title
- `short_name` (string, optional)
- `installed` (long, optional): Date when sticker set was installed, not present if sticker set is not installed.
- `count` (int, optional): Count of stickers
- `res` (array): Array of stickers
  - Sticker (object)
    - `id` (string): Document ID
    - `access_hash` (string): Document access hash
    - `mime` (string): MIME type

---

## `installStickerSet`

### Description
Saves sticker set.

Either `id` with `access_hash`, or `slug` have to be set.

Available since v7

### Parameters
- `id`: Sticker set ID
- `access_hash`: Sticker set access hash
- `slug`: Sticker set short name

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `uninstallStickerSet`

### Description
Removes sticker set from saved.

Either `id` with `access_hash`, or `slug` have to be set.

Available since v11

### Parameters
- `id`: Sticker set ID
- `access_hash`: Sticker set access hash
- `slug`: Sticker set short name

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `checkChatInvite`

### Description
Returns raw invite link information

See: https://docs.madelineproto.xyz/API_docs/types/ChatInvite.html

Available since v5

### Parameters
- `id`: Invite hash

### Response
Object

- `res`: MadelineProto `ChatInvite` object

---

## `importChatInvite`

### Description
Join chat by invite link

Available since v5

### Parameters
- `id`: Invite hash

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `getExportedChatInvites`

### Description
Returns created invite links.

See: https://docs.madelineproto.xyz/API_docs/types/messages.ExportedChatInvites.html

Available since v11

### Parameters
- `peer` [Peer ID](#Peer-ID)

### Response
Object

- `res`: MadelineProto `messages.exportedChatInvites` object

---

## `exportChatInvite`

### Description
Creates invite link.

See: https://docs.madelineproto.xyz/API_docs/types/ExportedChatInvite.html

Available since v11

### Parameters
- `peer` [Peer ID](#Peer-ID)

### Response
Object

- `res`: MadelineProto `ExportedChatInvite` object

---

## `addChatUser`

### Description
Adds a user to a chat.

Available since v11

### Parameters
- `peer`: [Peer ID](#Peer-ID) of chat
- `id`: User ID

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `deleteChatUser`

### Description
Removes a user from a chat.

Available since v11

### Parameters
- `peer`: [Peer ID](#Peer-ID) of chat
- `id`: User ID

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `inviteToChannel`

### Description
Invites users to a channel.

Available since v11

### Parameters
- `peer`: [Peer ID](#Peer-ID) of channel
- `id`: Comma-separated list of users

### Response
Object

- `res` (int): Count of missing invitees, 0 if every invite was sent successfully.

---

## `sendVote`

### Description
Votes in a poll.

Available since v11

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `id`: Message ID
- `options`: Comma-separated list of options data

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `getMessageReadParticipants`

### Descriptions
Returns list of participants who read a message in a chat.

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `id`: Message ID

### Response
Object

- `res` (array): Array of [User](#User) objects 

---

# Channels methods

## `joinChannel`

### Description
Join public channel.

Available since v5

### Parameters
- `id`: [Peer ID](#Peer-ID) of channel

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `leaveChannel`

### Description
Leave channel.

Available since v5

### Parameters
- `id`: [Peer ID](#Peer-ID) of channel

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `getParticipants`

### Description
Returns chat participants.

**Currently supports only channels.**

Available since v6

### Parameters
- `peer`: [Peer ID](#Peer-ID)
- `filter` (optional): `Recent` by default
- `offset` (optional): Offset, for pagination.
- `limit` (optional): Limit of users per response, for pagination.

### Response
Object

- `count` (int, optional): Count of chat participants
- `res` (array): Array of [User](#User) objects

---

## `banMember`

### Description
Bans a user from channel.

Available since v6

### Parameters
- `peer`: [Peer ID](#Peer-ID) of channel.
- `id`: User ID

### Response
Object

- `res` (int): 1 if request completed successfully

---

# Updates methods

## `updates`

### Description
Get updates.

Available since v5

### Parameters

- `offset`: Update ID offset, inclusive.
- `limit` (optional): Limit of updates per response, 100 by default.
- `types` (optional): Comma-separated list of update types to filter.
- `exclude` (optional): Comma-separated list of update types to exclude.
- `longpoll` (optional): Set to 0 disable long-poll, if set, method will not wait for new updates, 1 by default.
- `timeout` (optional): Timeout of long-poll in seconds, positive integer, 10 by default.
- `peer` (optional): Peer ID, if set, method will return only updates related to that peer.
- `top_msg` (optional): Top message ID of thread, set for forum chats with `peer`.
- `read` (optional): Set to 1 to automatically mark received messages as read, works only in `peer` mode.
- `media` (optional): Set to 1 to include media in messages.

### Response
Object

- `res` (array, optional): Array of [Update](#Update) objects
- `cancel` (int, optional): 1, if request was cancelled by [cancelUpdates](#cancelUpdates) method. **since v9**

---

## `getLastUpdate`

### Description
Returns last received update.

Available since v6

### Response
Object

- `res` (object): [Update](#Update) object

---

## `cancelUpdates`

### Description

Cancels [updates](#updates) method long-poll.

Available since v9

### Response

Object

- `res` (int): 1 if request completed successfully

<details>
<summary>Changes</summary>

#### Removed since v11

- `res` (boolean): true if request completed successfully. **Changed type to int.**'

</details>

---

## `updateStatus`

### Description

Updates online state of logged user.

Available since v6

### Parameters

- `off` (optional): set to 1 for offline

### Response
Object

- `res` (int): 1 if request completed successfully

---

## `notifications`

### Descriptions
Returns message updates.
<br>
Unlike [updates](#updates) method, this method is not long-polled, it should be called periodically in interval defined by user.
<br>
Cannot be used while [updates](#updates) method is active.

Available since v8

### Parameters

- `offset`: Update offset ID, inclusive.
- `limit` (optional): Limit of updates per response, 1000 by default.
- `include_muted` (optional): Set to 1 to include messages from muted peers.
- `peers` (optional): Comma-separated list of Peer IDs to filter.
- `media` (optional): Set to 1 to include media in messages
- `mu` (optional): set to 1 to mute users, see [getNotifySettings](#getNotifySettings) method.
- `mc` (optional): set to 1 to mute chats, see [getNotifySettings](#getNotifySettings) method.
- `mb` (optional): set to 1 to mute broadcast channels, see [getNotifySettings](#getNotifySettings) method.

<details>
<summary>Changes</summary>

#### Removed since v10

- `mute_users` (optional): set to 1 to mute users, see [getNotifySettings](#getNotifySettings) method. **replaced by `mu`**
- `mute_chats` (optional): set to 1 to mute chats, see [getNotifySettings](#getNotifySettings) method. **replaced by `mc`**
- `mute_broadcasts` (optional): set to 1 to mute broadcast channels, see [getNotifySettings](#getNotifySettings) method **replaced by `mb`**

</details>

### Response
Object

- `res` (array): Array of notifications
  - Notification (object)
    - `_` (string): Type of update
    - `message` (object): [Message](#Message) object
    - `mute` (boolean, optional): true if message is from muted peer, present if `include_muted` is set.
- `offset` (int): Offset for next request

---

## `getNotifySettings`

### Description
Returns notification settings of logged user.

Available since v8

### Response
Object

- `users` (long): Date until users are muted, or 0 if notifications are enabled.
- `chats` (long): Date until users are muted, or 0 if notifications are enabled.
- `broadcasts` (long): Date until users are muted, or 0 if notifications are enabled.


---

# Misc methods

## `getServerTimeOffset`

### Description
Returns server timezone.

Does not require authorization.

Available since v1

### Response
Object

- `res` (int): Timezone offset in seconds 

---

# Additional endpoints

## `file.php`

### GET Parameters
**Sticker:**
- `sticker`: Sticker ID
- `access_hash`: Sticker access hash
- `p` (optional): Conversion parameters, see below. If not set, will return webp.
- `s` (optional): Size parameter for `p`, 180 by default

**Message file:**
- `c`: Peer ID
- `m`: Message ID
- `p` (optional): Conversion parameters, see below.
- `s` (optional): Size parameter for `p`, 180 by default
- `tw` (optional): Target width for `p=rview`
- `th` (optional): Target height for `p=rview`

**`p` format:**

Parsed in order:

- `tgss`: Convert TGS to static PNG, end.
- `tgs`: Convert TGS to GIF, end.
- `thumb`: Get thumbnail of document
- `r`: Resize or convert
  - `stickerp`: (Sticker PNG) Convert to PNG, resize to fit width `s`, end.
  - `png`: Convert to PNG, end.
  - `r`: Rotate to 270 degrees
  - `orig`: Set JPEG quality to 80, keep original size, end.
  - `prev`: Set JPEG quality to 50, resize to fit `s` in both dimensions, end.
  - `min`: Set JPEG quality to 30, resize to fit 180x90, end.
  - `sticker`: Set JPEG quality to 75, resize to fit width `s` or height 90, end.
  - `sprev`: (Sticker preview) Set JPEG quality to 30, resize to fit width 100 or height 80, end.
  - `audio`: (Audio file): Set JPEG quality to 50, resize to fit height 36 or width 36, end.
  - `sprevs`: (Sticker preview with custom size): Set JPEG quality to 30, resize to fit width `s` or height `s`, end.
  - `view`: Set JPEG quality to 70, fit to `tw`x`th`, end.

Examples:

- `p=rprev&s=200`: Convert to JPEG with quality 50, resize to fit 200x200.
- `p=rview&tw=240&th=320`: Convert to JPEG with quality 70, resize to fit 240x320.
- `p=rrview&tw=320&th=240`: Convert to JPEG with quality 70, rotate to 270 degrees, resize to fit 320x240.
- `p=thumbraudio`: Get thumbnail of document, convert to JPEG with quality 50, resize to fit 36x36.
- `p=rpng`: Convert to PNG.
- `p=rorig`: Convert to JPEG.
- `p=rstickerp&s=120`: Convert to PNG, resize to fit 120x120.


### Errors
- HTTP 400: file is too large to download
- HTTP 401: unauthorized
- HTTP 403: feature is not enabled
- HTTP 500: server error

### Response
File

---

## `ava.php`

### Description
Returns profile picture.

### GET Parameters
- `c`: Peer ID
- `a` (optional): Set to 1 to return HTTP 404 on fail.
- `p`: Conversion parameters, see below.

**`p` format:**

- `r`
  - `p`: Convert to PNG
  - `orig`: Keep original size, end.
  - integer: Resize, end.

Examples:
- `p=r36`: Convert to JPEG, resize to 36x36.
- `p=r48`, Convert to PNG, resize to 48x48.
- `p=rorig`, Convert to JPEG, keep original size.
- `p=rporig`, Convert to PNG, keep original size.

### Response
File

---

## `voice.php`

### Description
Converts voice message from message to MP3 64 kbit/s.

### GET Parameters
- `c`: Peer ID
- `m`: Message ID

### Response
MP3 file

---

## `qrcode.php`

### Description
Utility for creating authorization QR code.

Does not require authorization.

### GET Parameters
- `t`: Base64 encoded text

### Response
PNG Image

---

# Methods availability by API versions
v1:
- `getCaptchaImg`
- `initLogin` **Changed purpose in v10, deprecated from v2 to v10**
- `completePhoneLogin`
- `complete2faLogin`
- `completeSignup` **Removed since v2**
- `logout` **Removed since v2, reintroduced in v11**
- `getServerTimeOffset`
- `getDialogs`
- `getAllDialogs` **Deprecated**
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
- `banMember`
- `getForumTopics`
- `botCallback` **Deprecated since v10, replaced by `sendBotCallback`**
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

v11:
- `uninstallStickerSet`
- `getMessageReadParticipants`
- `getExportedChatInvites`
- `exportChatInvite`
- `addChatUser`
- `deleteChatUser`
- `inviteToChannel`
- `sendVote`
- `logout`
