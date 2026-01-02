<?php

declare(strict_types=1);

namespace Datum\Events;

/**
 * Event fired after saving a model (both create and update).
 */
class Saved extends ModelEvent {}
