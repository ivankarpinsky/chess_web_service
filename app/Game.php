<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    const ERRORS = ['Wrong coordinates', 'This cell is empty', 'The figure is not yours', 'Impossible move', 'game is finished', 'wrong query parameters'];

    function gen_token()
    {
        $token = md5(microtime() . 'a395697813808b1ef7e25465861c3baf' . time());
        return $token;
    }

    public function start()
    {
        $token = $this->gen_token();
        self::insert(array(
            'token' => $token,
        ));

        return $this->response(201, json_encode(
            ['status' => true, 'token' => $token]
        ));
    }

    private function response($code, $json)
    {
        http_response_code($code);
        return $json;
    }

    public function status($token)
    {
        if (!(isset($data['token'])))
            return $this->badResponse(self::ERRORS[4]);

        $game = $this->where('token', '=', $token)->first();

        if ($game === null) return $this->response(404, json_encode(['status' => false, 'message' => 'game not found']));

        return $this->response(200, json_encode(
            [
                'field' => $game['field'],
                'turn' => ($game['whitesTurn']) ? 'white' : 'black',
                'finished' => $game['finished'],
                'winner' => $game['winner']
            ]
        ));
    }

    public function turn($data)
    {
        if (!(isset($data['token']) AND isset($data['x1']) AND isset($data['y1']) AND isset($data['x2']) AND isset($data['y2'])))
            return $this->badResponse(self::ERRORS[4]);
        $game = $this->where('token', '=', $data['token'])->first();

        if ($game == null) return $this->response(404, json_encode(['status' => false, 'message' => 'game not found']));
        if ($game['finished'] == 1) return $this->badResponse(self::ERRORS[5]);

        $yourColor = ($game['whitesTurn']) ? 'white' : 'black';
        $opponentColor = ($game['whitesTurn']) ? 'black' : 'white';
        $x1 = $data['x1'];
        $x2 = $data['x2'];
        $y1 = $data['y1'];
        $y2 = $data['y2'];
        $color = ($game['whitesTurn']) ? 'white' : 'black';
        $field = json_decode($game['field'], true);

        if (!($x1 >= 0 AND $x1 < 8 AND $x2 >= 0 AND $x2 < 8 AND
            $y1 >= 0 AND $y1 < 8 AND $y2 >= 0 AND $y2 < 8))
            return $this->badResponse(self::ERRORS[0]);

        if ($field[$x1][$y1] === null)
            return $this->badResponse(self::ERRORS[1]);

        if ($field[$x1][$y1]['color'] != $color)
            return $this->badResponse(self::ERRORS[2]);

        // проверка на возможность хода по правилам хода фигур
        $possibleCells = $this->possibleCells($field, $x1, $y1);

        if (!in_array([$x2, $y2], $possibleCells))
            return $this->badResponse(self::ERRORS[3]);

        // проверка на то, что ход не должен приводить к шаху
        $newField = $field;
        $newField[$x2][$y2] = $field[$x1][$y1];

        if ($field[$x1][$y1]['type'] == 'pawn' AND ($y2 == 7 or $y2 == 0))
            $newField[$x2][$y2]['type'] = 'queen';

        $newField[$x1][$y1] = null;

        $opponentFiguresCoordinates = $this->getAllFiguresCoordinates($field, $opponentColor);
        $possibleOpponentMoves = [];

        foreach ($opponentFiguresCoordinates as $cell) {
            $possibleOpponentMoves = array_merge($this->possibleCells($field, $cell[0], $cell[1], true), $possibleOpponentMoves);
        }

        $possibleOpponentMoves = array_unique($possibleOpponentMoves, SORT_REGULAR);

        if (in_array($this->getKing($field, $yourColor), $possibleOpponentMoves))
            return $this->badResponse(self::ERRORS[3]);

        $this->where('token', '=', $data['token'])->update(['field' => json_encode($newField), 'whitesTurn' => !$game['whitesTurn']]); // ход успешен
        // проверяем будет ли шах
        $check = false;
        $yourFigures = $this->getAllFiguresCoordinates($newField, $yourColor);
        $yourPossibleMoves = [];

        foreach ($yourFigures as $cell) {
            $yourPossibleMoves = array_merge($this->possibleCells($newField, $cell[0], $cell[1], true), $yourPossibleMoves);
        }

        $yourPossibleMoves = array_unique($yourPossibleMoves, SORT_REGULAR);

        if (in_array($this->getKing($newField, $opponentColor), $yourPossibleMoves))
            $check = true;

        // проверка на мат, каждая из вражеских фигур делает возможные ходы, затем вычисляется останется ли шах или нет
        // если была хоть одна ситуация, где шаха удалось избежать, тогда это не мат
        if ($check) {
            $mateCheck = false;
            $opponentFiguresCoordinates = $this->getAllFiguresCoordinates($newField, $opponentColor);

            foreach ($opponentFiguresCoordinates as $cell) {
                $possibleMoves = $this->possibleCells($newField, $cell[0], $cell[1]);

                foreach ($possibleMoves as $move) {
                    $veryNewField = $newField;
                    $veryNewField[$move[0]][$move[1]] = $newField[$cell[0]][$cell[1]];
                    if ($newField[$cell[0]][$cell[1]]['type'] == 'pawn' AND ($move[1] == 7 or $move[1] == 0))
                        $veryNewField[$move[0]][$move[1]] = 'queen';
                    $veryNewField[$cell[0]][$cell[1]] = null;

                    $yourFigures = $this->getAllFiguresCoordinates($newField, $yourColor);
                    $yourPossibleMoves = [];

                    foreach ($yourFigures as $cell2) {
                        $yourPossibleMoves = array_merge($this->possibleCells($newField, $cell2[0], $cell2[1], true), $yourPossibleMoves);
                    }

                    $yourPossibleMoves = array_unique($yourPossibleMoves, SORT_REGULAR);

                    if (!in_array($this->getKing($newField, $opponentColor), $yourPossibleMoves)) {
                        $mateCheck = true;
                        break;
                    }
                }
                if ($mateCheck) break;
            }

            if (!$mateCheck) {
                $this->where('token', '=', $data['token'])->update(['finished' => true, 'winner' => $yourColor]);
            }
        }

        return $this->response(200, json_encode(['status' => true, 'message' => 'successful move']));
    }

    private function badResponse($error)
    {
        http_response_code(400);
        return json_encode(['status' => false, 'message' => $error]);
    }

    private function possibleCells($field, $x1, $y1, $pawnEatOnly = false)
    {
        $figure = $field[$x1][$y1];
        $cells = [];

        switch ($figure['type']) {
            case 'castle':
                $cells = $this->castleMoves($field, $x1, $y1, $figure);
                break;
            case 'knight':
                $cells = $this->knightMoves($field, $x1, $y1, $figure);
                break;
            case 'bishop':
                $cells = $this->bishopMoves($field, $x1, $y1, $figure);
                break;
            case 'queen':
                $cells = array_merge($this->castleMoves($field, $x1, $y1, $figure), $this->bishopMoves($field, $x1, $y1, $figure));
                break;
            case 'king':
                $cells = $this->kingMoves($field,$x1,$y1,$figure);
                break;
            case 'pawn':
                $cells = $this->pawnMoves($field,$x1,$y1,$figure,$pawnEatOnly);
                break;
        }

        return $cells;
    }

    private function knightMoves($field, $x1, $y1, $figure) {
        $cells = [[$x1 + 1, $y1 + 2], [$x1 + 1, $y1 - 2], [$x1 - 1, $y1 + 2], [$x1 - 1, $y1 + 2], [$x1 + 2, $y1 + 1],
            [$x1 + 2, $y1 - 1], [$x1 - 2, $y1 + 1], [$x1 - 2, $y1 - 1]];

        foreach ($cells as $key => $cell) {
            if (!($cell[0] >= 0 AND $cell[0] < 8 AND $cell[1] >= 0 AND $cell[1] < 8)) {
                unset($cells[$key]);
                continue;
            }
            if ($field[$cell[0]][$cell[1]] != null AND $field[$cell[0]][$cell[1]]['color'] == $figure['color']) {
                unset($cells[$key]);
            }
        };
    }

    private function kingMoves($field, $x1,$y1,$figure) {
        $cells = [[$x1 + 1, $y1 + 1], [$x1 + 1, $y1], [$x1 + 1, $y1 - 1], [$x1, $y1 - 1], [$x1 - 1, $y1 - 1],
            [$x1 - 1, $y1], [$x1 - 1, $y1 + 1], [$x1, $y1 + 1]];

        foreach ($cells as $key => $cell) {
            if (!($cell[0] >= 0 AND $cell[0] < 8 AND $cell[1] >= 0 AND $cell[1] < 8)) {
                unset($cells[$key]);
                continue;
            }
            if ($field[$cell[0]][$cell[1]] != null AND $field[$cell[0]][$cell[1]]['color'] == $figure['color']) {
                unset($cells[$key]);
            }
        }

        return $cells;
    }

    private function pawnMoves($field, $x1, $y1, $figure, $eatOnly=false) {
        if ($figure['color'] == 'black') {
            if ($y1 > 0 AND $field[$x1][$y1 - 1] == null AND !$eatOnly) {
                $cells[] = [$x1, $y1 - 1];
                if ($y1 == 6 AND $field[$x1][$y1 - 2] == null) $cells[] = [$x1, $y1 - 2];
            }
            if ($y1 > 0 AND $x1 < 7 AND $field[$x1 + 1][$y1 - 1]['color'] == 'white') $cells[] = [$x1 + 1, $y1 - 1];
            if ($y1 > 0 AND $x1 > 0 AND $field[$x1 - 1][$y1 - 1]['color'] == 'white') $cells[] = [$x1 - 1, $y1 - 1];
        } else {
            if ($y1 < 7 AND $field[$x1][$y1 + 1] == null AND !$eatOnly) {
                $cells[] = [$x1, $y1 + 1];
                if ($y1 == 1 AND $field[$x1][$y1 + 2] == null) $cells[] = [$x1, $y1 + 2];
            }
            if ($y1 < 7 AND $x1 < 7 AND $field[$x1 + 1][$y1 + 1]['color'] == 'black') $cells[] = [$x1 + 1, $y1 + 1];
            if ($y1 < 7 AND $x1 > 0 AND $field[$x1 - 1][$y1 + 1]['color'] == 'black') $cells[] = [$x1 - 1, $y1 + 1];
        }

        return $cells;
    }

    private function castleMoves($field, $x1, $y1, $figure)
    {
        $cells = [];
        $i = $x1;
        $j = $y1;

        do {
            $j++;
            if ($j > 7 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        $j = $y1;

        do {
            $j--;
            if ($j < 0 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        $j = $y1;

        do {
            $i--;
            if ($i < 0 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        $i = $x1;

        do {
            $i++;
            if ($i > 7 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        return $cells;
    }

    private function bishopMoves($field, $x1, $y1, $figure)
    {
        $cells = [];
        $i = $x1;
        $j = $y1;

        do {
            $i++;
            $j++;
            if ($i > 7 OR $j > 7 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        $i = $x1;
        $j = $y1;

        do {
            $i++;
            $j--;
            if ($i > 7 OR $j < 0 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        $i = $x1;
        $j = $y1;

        do {
            $i--;
            $j--;
            if ($i < 0 OR $j < 0 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        $i = $x1;
        $j = $y1;

        do {
            $i--;
            $j++;
            if ($i < 0 OR $j > 7 OR $field[$i][$j] != null AND $field[$i][$j]['color'] == $figure['color']) break;
            $cells[] = [$i, $j];
        } while ($field[$i][$j] == null OR $field[$i][$j] != null AND $field[$i][$j]['color'] != $figure['color']);

        return $cells;
    }

    private function getAllFiguresCoordinates($field, $color)
    {
        $cells = [];

        foreach ($field as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell['color'] == $color)
                    $cells[] = [$y, $x];
            }
        }

        return $cells;
    }

    private function getKing($field, $color)
    {
        foreach ($field as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell['color'] == $color AND $cell['type'] == 'king')
                    return [$y, $x];
            }
        }
    }
}
