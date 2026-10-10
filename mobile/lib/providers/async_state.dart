enum LoadStatus { initial, loading, success, empty, error }

class AsyncState<T> {
  const AsyncState({
    this.status = LoadStatus.initial,
    this.data,
    this.errorMessage,
  });

  final LoadStatus status;
  final T? data;
  final String? errorMessage;

  bool get isLoading => status == LoadStatus.loading;
  bool get isSuccess => status == LoadStatus.success;
  bool get isError => status == LoadStatus.error;
  bool get isEmpty => status == LoadStatus.empty;

  AsyncState<T> copyWith({
    LoadStatus? status,
    T? data,
    String? errorMessage,
    bool clearError = false,
  }) {
    return AsyncState<T>(
      status: status ?? this.status,
      data: data ?? this.data,
      errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    );
  }

  factory AsyncState.loading([T? data]) =>
      AsyncState<T>(status: LoadStatus.loading, data: data);

  factory AsyncState.success(T data) =>
      AsyncState<T>(status: LoadStatus.success, data: data);

  factory AsyncState.empty([T? data]) =>
      AsyncState<T>(status: LoadStatus.empty, data: data);

  factory AsyncState.error(String message, [T? data]) =>
      AsyncState<T>(status: LoadStatus.error, data: data, errorMessage: message);
}
