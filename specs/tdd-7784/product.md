# Product Spec : Reliable base64 recognition for stored media

Ticket : TDD-7784, branch `tdd-7784`

## Problem Statement

Applications hand content to this package to store it as a media item. The package first decides what kind of content it received: base64-encoded text, a web address, or raw content.

Today, any content containing the text `;base64,` anywhere is treated as a possible base64 data URI and cut apart at its first `;` and first `,`. A raw PDF file whose bytes happen to contain `;base64,`, with a `;` earlier in the file, makes that cut fail and the whole storing operation crashes. In worksite-api this blocked the creation of expenses from two Storecove receivings (TDD-7752): the attachment could not be stored, so the expense was never created.

The same loose recognition also accepts content that is not base64 at all: a web address such as `http://internal-host/x;base64,QQ==` and empty text are both recognised as base64 today. worksite-api already tightened its own check for this reason (TDD-5946), so the two now disagree on what counts as base64.

Cause: the package looks for the data URI marker anywhere in the content instead of only as a well-formed prefix at the very start.

Left alone, any received document whose bytes contain the marker keeps failing to store, and applications relying on this package accept address-like text as encoded content.

## Actors

-   Consuming application : an application (worksite-api, trustup-io-media, trustup-io-messaging, laravel-trustup-media-io) that hands content to this package to be stored as a media item
-   Worksite application (the worksite-api repository) : also checks text for base64 on its own, outside of storing media, and must reach the same verdict as this package

## Acceptance Criteria

### AC-1 : Raw content containing the base64 marker is stored as raw content

-   **Who** : Consuming application
-   **Given** : a raw file (for example a PDF) whose bytes contain `;base64,` somewhere other than as a well-formed data URI prefix at the very start
-   **When** : the application hands that content to the package to be stored
-   **Then** : the system shall recognise it as raw content and prepare it for storage as such, without failing

### AC-2 : Well-formed base64 content is still recognised

-   **Who** : Consuming application
-   **Given** : content that is either a data URI starting with `data:<type>/<subtype>;base64,` followed by valid, non-empty base64 text, or valid, non-empty base64 text on its own
-   **When** : the application hands that content to the package to be stored
-   **Then** : the system shall recognise it as base64 content, exactly as it does today

### AC-3 : One shared base64 check for other applications

-   **Who** : Worksite application
-   **Given** : a piece of text, without any media item built around it
-   **When** : the worksite application asks the package whether that text is base64
-   **Then** : the system shall answer with the same verdict it applies when storing media (AC-1, AC-2 and the edge cases below)

## Edge Cases

-   Data URI marker after other text (for example `http://internal-host/x;base64,QQ==`) : not recognised as base64. Today it is, so this is an intended tightening.
-   Empty text : not recognised as base64. Today it is, so this is an intended change.
-   A data URI with extra parameters before the marker (for example `data:application/pdf;name=x.pdf;base64,...`) : not recognised as base64. Today it makes the storing operation crash, so nothing that works today stops working.
-   Capitals in the `data:` or `;base64,` markers (for example `DATA:image/png;BASE64,QQ==`) : not recognised as base64, as today. The storage side only understands the lower-case markers, so recognising them would turn content stored today into a failure.
-   Capitals in the media type only (for example `data:image/PNG;base64,QQ==`) : recognised, as today.
-   A well-formed data URI prefix followed by nothing, or by text that is not valid base64 : not recognised as base64.
-   Text that is valid base64 only once whitespace or line breaks are ignored : not recognised as base64, as today.

## Out of Scope

-   Changing worksite-api so its own base64 check relies on this package, requiring the package directly there, and covering a received PDF containing the marker in the expense creation flow: separate step in worksite-api, once the fixed version is released.
-   Releasing the fixed version: the change goes through a pull request from a fork, and someone with write access on the package repository merges it and publishes the next patch version.
-   Accepting data URIs with extra parameters.
-   Recognising a web address or any other kind of content differently: only the base64 decision changes.
-   The other consuming applications (trustup-io-media, trustup-io-messaging, laravel-trustup-media-io) get the stricter recognition when they next update the package; no change is planned in them.
