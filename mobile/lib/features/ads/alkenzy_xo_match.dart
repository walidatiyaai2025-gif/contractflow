/// One X/O game is a non-transferable, in-app Rewarded Ad benefit.
/// The board does not depend on WordPress, accounts or internet requests.
enum XoPiece { empty, human, computer }

enum XoOutcome { playing, humanWon, computerWon, draw }

final class AlkenzyXoMatch {
  AlkenzyXoMatch({List<XoPiece>? initial})
    : cells = List<XoPiece>.of(
        initial ?? List<XoPiece>.filled(9, XoPiece.empty),
      ) {
    if (cells.length != 9) {
      throw ArgumentError.value(cells.length, 'initial', 'Expected 9 cells');
    }
  }

  final List<XoPiece> cells;

  static const _winningLines = <List<int>>[
    [0, 1, 2],
    [3, 4, 5],
    [6, 7, 8],
    [0, 3, 6],
    [1, 4, 7],
    [2, 5, 8],
    [0, 4, 8],
    [2, 4, 6],
  ];

  XoOutcome get outcome {
    for (final line in _winningLines) {
      final mark = cells[line[0]];
      if (mark != XoPiece.empty &&
          mark == cells[line[1]] &&
          mark == cells[line[2]]) {
        return mark == XoPiece.human
            ? XoOutcome.humanWon
            : XoOutcome.computerWon;
      }
    }
    return cells.contains(XoPiece.empty) ? XoOutcome.playing : XoOutcome.draw;
  }

  bool humanMove(int index) {
    if (index < 0 ||
        index >= 9 ||
        cells[index] != XoPiece.empty ||
        outcome != XoOutcome.playing) {
      return false;
    }
    cells[index] = XoPiece.human;
    if (outcome == XoOutcome.playing) {
      final response = chooseComputerMove();
      if (response != null) cells[response] = XoPiece.computer;
    }
    return true;
  }

  int? chooseComputerMove() {
    if (outcome != XoOutcome.playing) return null;
    for (final piece in <XoPiece>[XoPiece.computer, XoPiece.human]) {
      for (var i = 0; i < cells.length; i++) {
        if (cells[i] != XoPiece.empty) continue;
        cells[i] = piece;
        final result = outcome;
        cells[i] = XoPiece.empty;
        if ((piece == XoPiece.computer && result == XoOutcome.computerWon) ||
            (piece == XoPiece.human && result == XoOutcome.humanWon)) {
          return i;
        }
      }
    }
    for (final index in <int>[4, 0, 2, 6, 8, 1, 3, 5, 7]) {
      if (cells[index] == XoPiece.empty) return index;
    }
    return null;
  }
}
